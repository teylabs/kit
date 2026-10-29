<?php

namespace Tey\Kit\Features\Mcp\Listeners;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Token;
use Tey\Kit\Features\Mcp\Http\KitAuthorizationController;

/**
 * Moves a live connection onto the access token that replaced its own, so it outlives the one-hour token it is bound to.
 * A refresh revokes the old token before issuing the new one; only a connection whose token is spent (revoked or expired)
 * and was issued to the same user and client follows. A revoked connection, a live token's connection, or another user's
 * or client's connection never moves: no match leaves no connection for the new token.
 *
 * When nothing moved, the token is a fresh one from a code exchange: it takes the newest connection approved on the consent screen for the same user and client that is
 * still waiting for its token (`BindConsentWorkspace`), and that connection now expires with the token. With none waiting, a connection Passport's skipped consent chose
 * to reuse (`KitAuthorizationController`) moves onto it instead.
 */
class RebindConnection
{
    public function handle(AccessTokenCreated $event): void
    {
        $token = Token::query()->find($event->tokenId);
        if ($token === null || $event->userId === null || $token->revoked || (string) $token->user_id !== (string) $event->userId || (string) $token->client_id !== (string) $event->clientId || ! $token->can('mcp:use')) {
            return;
        }

        DB::transaction(function () use ($token): void {
            $spent = Token::query()->where('user_id', $token->user_id)->where('client_id', $token->client_id)->whereKeyNot($token->getKey())
                ->where(fn ($query) => $query->where('revoked', true)->orWhere('expires_at', '<=', now()))->select('id');
            $moved = DB::table('connections')->where('actor_id', $token->user_id)->whereNull('revoked_at')->whereIn('oauth_token_id', $spent)
                ->update(['oauth_token_id' => $token->getKey(), 'expires_at' => $token->expires_at]);
            if ($moved > 0) {
                return;
            }
            $pending = DB::table('connections')->where('actor_id', $token->user_id)->where('oauth_client_id', (string) $token->client_id)->whereNull('oauth_token_id')->whereNull('revoked_at')
                ->where('expires_at', '>', now())->orderByDesc('id')->lockForUpdate()->value('id');
            // Consent skipped (KitAuthorizationController): the live connection it found in the current workspace moves onto the new token.
            $pending ??= DB::table('connections')->where('id', Cache::pull(KitAuthorizationController::reuseKey($token->user_id, $token->client_id)))->where('actor_id', $token->user_id)
                ->where('oauth_client_id', (string) $token->client_id)->whereNull('revoked_at')->value('id');
            if ($pending !== null) {
                DB::table('connections')->where('id', $pending)->update(['oauth_token_id' => $token->getKey(), 'expires_at' => $token->expires_at]);
            }
        });
    }
}

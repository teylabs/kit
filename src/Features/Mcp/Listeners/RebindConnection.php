<?php

namespace Tey\Kit\Features\Mcp\Listeners;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Events\AccessTokenRevoked;
use Laravel\Passport\Token;
use Tey\Kit\Features\Mcp\Http\KitAuthorizationController;
use Tey\Kit\Features\Mcp\McpProvider;

/**
 * Binds a new access token to the one connection it belongs to, and never to any other.
 *
 * Telling a refresh from a code exchange: league's refresh-token grant revokes the previous access token through Passport's repository first, with the id it reads from
 * the refresh token being used (`oauth_refresh_tokens.access_token_id`), which fires `AccessTokenRevoked` for exactly that token; only then does it issue the new access
 * token (`AccessTokenCreated`). The auth-code grant revokes no access token. So `revoked` records each access token revoked in this request, and a new token is a refresh
 * when one of them belongs to the same user and client and a refresh token links to it. The record is request-scoped (a scoped binding), so nothing carries between
 * requests or jobs.
 *
 * - A refresh moves only the connections bound to the refreshed token (normally one) onto the new token, unless they are revoked.
 * - A code exchange binds only the newest connection approved on the consent screen for this user and client that is still waiting for its token
 *   (`BindConsentWorkspace`), or failing that the connection Passport's skipped consent chose to reuse (`KitAuthorizationController`).
 * - Anything else binds nothing: a connection whose token expired without a refresh stays expired, and another user's or client's token never takes a connection.
 */
class RebindConnection
{
    /** @var list<string> access token ids revoked through Passport's repository during this request */
    private array $revoked = [];

    public function revoked(AccessTokenRevoked $event): void
    {
        $this->revoked[] = (string) $event->tokenId;
    }

    public function handle(AccessTokenCreated $event): void
    {
        $token = Token::query()->find($event->tokenId);
        if ($token === null || $event->userId === null || $token->revoked || (string) $token->user_id !== (string) $event->userId || (string) $token->client_id !== (string) $event->clientId || ! $token->can('mcp:use')) {
            return;
        }

        DB::transaction(function () use ($token): void {
            $previous = $this->refreshed($token);
            if ($previous !== null) {
                DB::table('connections')->where('actor_id', $token->user_id)->where('oauth_token_id', $previous)->whereNull('revoked_at')
                    ->update(['oauth_token_id' => $token->getKey(), 'expires_at' => $token->expires_at]);

                return;
            }
            // A host with its own consent keeps its own grants: the kit's consent made no connection for this token to take.
            if (McpProvider::consent() === 'host') {
                return;
            }
            $pending = DB::table('connections')->where('actor_id', $token->user_id)->where('oauth_client_id', (string) $token->client_id)->whereNull('oauth_token_id')->whereNull('revoked_at')
                ->where('expires_at', '>', now())->orderByDesc('id')->lockForUpdate()->value('id');
            $pending ??= DB::table('connections')->where('id', Cache::pull(KitAuthorizationController::reuseKey($token->user_id, $token->client_id)))->where('actor_id', $token->user_id)
                ->where('oauth_client_id', (string) $token->client_id)->whereNull('revoked_at')->value('id');
            if ($pending !== null) {
                DB::table('connections')->where('id', $pending)->update(['oauth_token_id' => $token->getKey(), 'expires_at' => $token->expires_at]);
            }
        });
    }

    /** The access token this one refreshes: revoked in this request, the same user and client, and named by a refresh token's access_token_id. Null for a code exchange. */
    private function refreshed(Token $token): ?string
    {
        foreach ($this->revoked as $index => $id) {
            $previous = Token::query()->whereKey($id)->where('user_id', $token->user_id)->where('client_id', $token->client_id)->exists();
            if ($previous && DB::table('oauth_refresh_tokens')->where('access_token_id', $id)->exists()) {
                unset($this->revoked[$index]);

                return $id;
            }
        }

        return null;
    }
}

<?php

namespace Tey\Kit\Features\Mcp;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use RuntimeException;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;

final class Context
{
    public static function principal(string $scope): Principal
    {
        $user = request()->user();
        $principal = request()->attributes->get('mcp.principal');
        $token = Users::is($user) ? $user->currentAccessToken() : null;
        $fresh = $token instanceof AccessToken ? Token::query()->whereKey($token->oauth_access_token_id)->where('user_id', $user->getKey())->where('client_id', $token->oauth_client_id)->where('revoked', false)->where('expires_at', '>', now())->first() : null;
        if (! $principal instanceof Principal || $principal->actorId !== (int) $user?->getKey() || ! $token || ! $fresh?->can('mcp:use') || ! WorkspaceConnection::tokenCovers((array) $token->oauth_scopes, $scope) || ! WorkspaceConnection::tokenCovers($fresh->scopes, $scope) || ! $fresh->client || $fresh->client->revoked) {
            throw new RuntimeException('connection_denied');
        }

        $connection = DB::table('connections')->where('id', $principal->connectionId)->where('actor_id', $principal->actorId)->where('oauth_token_id', $fresh->id)->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        // The connection must cover the ability too, so a narrow connection stays narrow whatever its token holds.
        if (! $connection || ! WorkspaceConnection::covers((array) json_decode($connection->scopes, true), $scope)) {
            throw new RuntimeException('connection_denied');
        }

        return $principal;
    }
}

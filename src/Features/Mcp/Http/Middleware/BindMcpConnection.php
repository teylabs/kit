<?php

namespace Tey\Kit\Features\Mcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\Workspaces\Workspaces;

class BindMcpConnection
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        $token = Users::is($user) ? $user->currentAccessToken() : null;
        abort_unless($request->bearerToken() !== null && $token instanceof AccessToken, 401);
        $fresh = Token::query()->whereKey($token->oauth_access_token_id)->where('user_id', $user->id)->where('revoked', false)->where('expires_at', '>', now())->first();
        // No live token row (revoked, expired, another person's): a 401 on the one address, so the assistant authorizes again; a connection's own address keeps its 403.
        abort_unless($fresh, $request->route('connection') === null ? 401 : 403);
        abort_unless($token->can('mcp:use') && $fresh->client_id === $token->oauth_client_id && $fresh->can('mcp:use') && $fresh->client && ! $fresh->client->revoked && $fresh->client->hasGrantType('authorization_code'), 403);
        $origin = $request->header('Origin');
        abort_if($origin !== null && $origin !== rtrim(config('app.url'), '/'), 403);
        $live = DB::table('connections')->where('actor_id', $user->id)->where('oauth_token_id', $fresh->id)->whereNull('revoked_at')->where('expires_at', '>', now());
        if ($request->route('connection') === null) {
            // The one address (/mcp): the token's own connection. None (unbound, revoked, expired) or more than one is a 401, so the assistant authorizes again.
            $matches = $live->limit(2)->get();
            abort_unless($matches->count() === 1, 401);
            $connection = $matches->first();
        } else {
            // A connection's own address must name the token's connection.
            $connection = $live->where('id', $request->route('connection'))->first();
            abort_unless($connection, 403);
        }
        $principal = new Principal((int) $user->id, (int) $connection->id);
        $request->attributes->set('mcp.principal', $principal);
        $request->attributes->set('mcp.client', $fresh->client->name);

        return Workspaces::run((int) $connection->workspace_id, function () use ($principal, $request, $next) {
            app(Access::class)->authorize($principal, WorkspaceConnection::baseAbility());

            return $next($request);
        });
    }
}

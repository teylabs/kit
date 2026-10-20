<?php

namespace Tey\Kit\Features\Mcp\Http;

use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\Workspaces\TenantBoundary;

class ConnectionController
{
    public function store(Request $request, int $workspace): mixed
    {
        $result = app(TenantBoundary::class)->run($workspace, fn () => app(WorkspaceConnection::class)->approve(new Principal((int) $request->user()->getKey()), $request->except(['_token'])));

        return $request->expectsJson() ? response()->json($result, $result['created'] ? 201 : 200) : redirect(WorkspaceUrl::to($workspace, 'connections', 'mcp'));
    }

    public function index(Request $request, int $workspace): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace) {
            $base = WorkspaceConnection::baseAbility();
            app(Access::class)->authorize(new Principal((int) $request->user()->getKey()), $base);
            $tokens = Token::query()->where('user_id', $request->user()->getKey())->where('revoked', false)->where('expires_at', '>', now())->get()->filter(fn ($token) => $token->can('mcp:use') && $token->can($base) && $token->client && ! $token->client->revoked && $token->client->hasGrantType('authorization_code'));
            $connections = DB::table('connections')->where('workspace_id', $workspace)->where('actor_id', $request->user()->getKey())->whereNotNull('oauth_token_id')->get();
            $scopes = array_intersect_key(app(Registry::class)->tokenScopes(), array_flip(WorkspaceConnection::grantableScopes()));

            return view('kit::mcp.connections', compact('workspace', 'tokens', 'connections', 'base', 'scopes'));
        });
    }

    public function destroy(Request $request, int $workspace, int $connection): mixed
    {
        $result = app(TenantBoundary::class)->run($workspace, fn () => app(WorkspaceConnection::class)->revoke(new Principal((int) $request->user()->getKey()), $connection));

        return $request->expectsJson() ? response()->json($result) : redirect(WorkspaceUrl::to($workspace, 'connections', 'mcp'));
    }
}

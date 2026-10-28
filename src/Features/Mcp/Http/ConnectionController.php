<?php

namespace Tey\Kit\Features\Mcp\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Passport\Token;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\TableColumn;
use Tey\Kit\Features\Core\Data\TableData;
use Tey\Kit\Features\Core\Data\TablePagination;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\AssistantClients;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\Mcp\ViewModels\ConnectionsViewModel;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

class ConnectionController
{
    public function store(Request $request, int $workspace): mixed
    {
        $result = app(WorkspaceConnection::class)->approve(new Principal((int) $request->user()->getKey()), $request->except(['_token']));

        return $request->expectsJson() ? response()->json($result, $result['created'] ? 201 : 200) : redirect(WorkspaceUrl::to($workspace, 'settings/connections', 'mcp'));
    }

    public function index(Request $request, int $workspace): mixed
    {
        // Hidden until the connection rework (see kit.mcp.ui).
        abort_unless(config('kit.mcp.ui', false), 404);

        $base = WorkspaceConnection::baseAbility();
        app(Access::class)->authorize(new Principal((int) $request->user()->getKey()), $base);
        $tokens = Token::query()->where('user_id', $request->user()->getKey())->where('revoked', false)->where('expires_at', '>', now())->get()->filter(fn ($token) => $token->can('mcp:use') && WorkspaceConnection::tokenCovers($token->scopes, $base) && $token->client && ! $token->client->revoked && $token->client->hasGrantType('authorization_code'));
        $connections = DB::table('connections')->where('workspace_id', $workspace)->where('actor_id', $request->user()->getKey())->whereNotNull('oauth_token_id')->get();
        $scopes = array_intersect_key(app(Registry::class)->tokenScopes(), array_flip(WorkspaceConnection::grantableScopes()));

        $rows = $connections->filter(fn ($connection) => $connection->revoked_at === null)->map(function ($connection) use ($workspace, $request): array {
            $token = Token::query()->with('client')->find($connection->oauth_token_id);

            return [
                'id' => (int) $connection->id,
                'assistant' => $token?->client?->name ?? 'AI assistant',
                'connectedBy' => (string) $request->user()->name,
                'connected' => $token?->created_at?->toIso8601String(),
                'expires' => $connection->expires_at,
                'actions' => [new Action('Revoke', WorkspaceUrl::to($workspace, 'settings/connections/'.$connection->id, 'mcp'), 'delete', confirm: ['title' => 'Revoke this assistant?', 'description' => 'It loses access to this workspace straight away. You can approve it again later.', 'label' => 'Revoke access'], key: 'revoke', destructive: true)],
            ];
        })->values()->all();
        $table = new TableData($rows, [new TableColumn('assistant', 'Assistant'), new TableColumn('connectedBy', 'Connected by'), new TableColumn('connected', 'Connected'), new TableColumn('expires', 'Expires'), new TableColumn('actions', 'Actions', align: 'end', srOnlyLabel: true)], null, null, [], [], new TablePagination(1, max(count($rows), 1), count($rows), $rows === [] ? null : 1, $rows === [] ? null : count($rows), 1), $rows === [] ? 'first-use' : null);
        $serverUrl = (string) (config('kit.mcp.server_url') ?: url('/mcp'));
        $name = (string) config('app.name');

        $model = new ConnectionsViewModel(
            'AI assistants',
            $tokens->map(fn ($token): array => ['id' => (string) $token->id, 'label' => $token->client->name.' · '.$token->created_at.' · expires '.$token->expires_at])->values()->all(),
            array_map(fn (string $scope, string $description): array => ['key' => $scope, 'description' => $description, 'required' => $scope === $base], array_keys($scopes), array_values($scopes)),
            $table,
            // Approve: absent when no assistant is waiting.
            $tokens->isEmpty() ? null : new Action('Approve', WorkspaceUrl::to($workspace, 'settings/connections', 'mcp'), 'post'),
            (string) Str::uuid(),
            $serverUrl,
            AssistantClients::for($serverUrl, $name),
        );

        return $request->expectsJson() && ! $request->header('X-Inertia') ? response()->json($model) : Inertia::render('Workspace/Settings/Connections/Page', $model);
    }

    public function destroy(Request $request, int $workspace, int $connection): mixed
    {
        $result = app(WorkspaceConnection::class)->revoke(new Principal((int) $request->user()->getKey()), $connection);

        return $request->expectsJson() ? response()->json($result) : redirect(WorkspaceUrl::to($workspace, 'settings/connections', 'mcp'));
    }
}

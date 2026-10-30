<?php

namespace Tey\Kit\Features\Mcp\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Mcp\AssistantClients;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\Mcp\ViewModels\ConnectionsViewModel;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

class ConnectionController
{
    public function store(Request $request, int $workspace): mixed
    {
        $result = app(TenantBoundary::class)->run($workspace, fn () => app(WorkspaceConnection::class)->approve(new Principal((int) $request->user()->getKey()), $request->except(['_token'])));

        return $request->expectsJson() ? response()->json($result, $result['created'] ? 201 : 200) : redirect(WorkspaceUrl::to($workspace, 'settings/connections', 'mcp'));
    }

    public function index(Request $request, int $workspace): mixed
    {
        // Hidden until the connection rework (see kit.mcp.ui).
        abort_unless(config('kit.mcp.ui', false), 404);

        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace) {
            $base = WorkspaceConnection::baseAbility();
            $principal = new Principal((int) $request->user()->getKey());
            app(Access::class)->authorize($principal, $base);
            // Only someone with the manage ability sees (and may revoke) the other members' connections; everyone else sees their own.
            $manages = AuthorizationView::allows($principal->actorId, $workspace, WorkspaceConnection::manageAbility());
            $bound = DB::table('connections')->where('actor_id', $request->user()->getKey())->whereNull('revoked_at')->whereNotNull('oauth_token_id')->pluck('oauth_token_id')->all();
            $tokens = Token::query()->where('user_id', $request->user()->getKey())->where('revoked', false)->where('expires_at', '>', now())->whereNotIn('id', $bound)->get()->filter(fn ($token) => $token->can('mcp:use') && WorkspaceConnection::tokenCovers($token->scopes, $base) && $token->client && ! $token->client->revoked && $token->client->hasGrantType('authorization_code'));
            $connections = DB::table('connections')->where('workspace_id', $workspace)->when(! $manages, fn ($query) => $query->where('actor_id', $request->user()->getKey()))->whereNotNull('oauth_token_id')->whereNull('revoked_at')->orderBy('id')->get();
            $names = Users::model()::query()->whereIn('id', $connections->pluck('actor_id')->unique()->all())->pluck('name', 'id');
            $scopes = array_intersect_key(app(Registry::class)->tokenScopes(), array_flip(WorkspaceConnection::grantableScopes()));

            $explicit = app(Registry::class)->explicitScopes();
            $rows = $connections->map(function ($connection) use ($workspace, $names, $explicit): array {
                $token = Token::query()->with('client')->find($connection->oauth_token_id);
                // The explicit grants this connection holds, each removable on its own; the connection and its token stay.
                $held = array_values(array_filter((array) json_decode($connection->scopes, true), fn (string $scope): bool => isset($explicit[$scope])));
                $removals = array_map(fn (string $scope): Action => new Action('Remove '.$explicit[$scope]['label'], WorkspaceUrl::to($workspace, 'settings/connections/'.$connection->id.'/grants/'.$scope, 'mcp'), 'delete',
                    confirm: ['title' => 'Remove this grant?', 'description' => 'The assistant keeps its connection but can no longer '.lcfirst(rtrim($explicit[$scope]['explains'], '.')).'. You can grant it again on its next connection.', 'label' => 'Remove grant'], key: 'remove-grant:'.$scope), $held);

                return [
                    'id' => (int) $connection->id,
                    'assistant' => $token?->client?->name ?? 'AI assistant',
                    'connectedBy' => (string) ($names[$connection->actor_id] ?? ''),
                    'connected' => $token?->created_at?->toIso8601String(),
                    'expires' => $connection->expires_at === null ? null : Carbon::parse($connection->expires_at, (string) config('app.timezone'))->toIso8601String(),
                    'grants' => $held === [] ? '' : implode(', ', array_map(fn (string $scope): string => $explicit[$scope]['label'], $held)),
                    'actions' => [...$removals, new Action('Revoke', WorkspaceUrl::to($workspace, 'settings/connections/'.$connection->id, 'mcp'), 'delete', confirm: ['title' => 'Revoke this assistant?', 'description' => 'It loses access to this workspace straight away. You can approve it again later.', 'label' => 'Revoke access'], key: 'revoke', destructive: true)],
                ];
            })->values()->all();
            $table = new TableData($rows, [new TableColumn('assistant', 'Assistant'), new TableColumn('connectedBy', 'Connected by'), new TableColumn('connected', 'Connected', date: 'date'), new TableColumn('expires', 'Expires', date: 'date'), new TableColumn('grants', 'Also allowed'), new TableColumn('actions', 'Actions', align: 'end', srOnlyLabel: true)], null, null, [], [], new TablePagination(1, max(count($rows), 1), count($rows), $rows === [] ? null : 1, $rows === [] ? null : count($rows), 1), $rows === [] ? 'first-use' : null);
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
        });
    }

    public function destroy(Request $request, int $workspace, int $connection): mixed
    {
        $result = app(TenantBoundary::class)->run($workspace, fn () => app(WorkspaceConnection::class)->revoke(new Principal((int) $request->user()->getKey()), $connection));

        return $request->expectsJson() ? response()->json($result) : redirect(WorkspaceUrl::to($workspace, 'settings/connections', 'mcp'));
    }
}

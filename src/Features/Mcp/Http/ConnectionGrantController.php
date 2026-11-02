<?php

namespace Tey\Kit\Features\Mcp\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** Removes one explicit grant from a connection on the AI assistants page, leaving the connection in place: fewer all-or-nothing revocations. */
final class ConnectionGrantController
{
    public function __invoke(Request $request, int $workspace, int $connection, string $scope): mixed
    {
        $result = app(TenantBoundary::class)->run($workspace, fn () => app(WorkspaceConnection::class)->removeGrant(new Principal((int) $request->user()->getKey()), $connection, $scope));

        return $request->expectsJson() ? response()->json($result) : redirect(WorkspaceUrl::to($workspace, 'settings/connections', 'mcp'));
    }
}

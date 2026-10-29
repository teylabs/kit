<?php

namespace Tey\Kit\Features\Workspaces;

/**
 * @deprecated Use `Workspaces::run($workspace, function () { ... })`. Inside a workspace request nothing is needed: ResolveWorkspace has
 * already made the workspace current. Removed in 0.7.
 */
final class TenantBoundary
{
    public function run(int $workspaceId, callable $operation): mixed
    {
        return Workspaces::run($workspaceId, function () use ($operation): mixed {
            return $operation();
        });
    }
}

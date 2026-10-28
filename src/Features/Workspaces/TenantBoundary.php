<?php

namespace Tey\Kit\Features\Workspaces;

/**
 * @deprecated 0.5 Make the workspace current instead: `Workspaces::query()->findOrFail($id)->execute(function () { ... })`. Inside a workspace
 * request nothing is needed, as the workspace is already current. The switch tasks set Concierge's context and flush Pennant. Removed in 0.6.
 */
final class TenantBoundary
{
    public function run(int $workspaceId, callable $operation): mixed
    {
        return Workspaces::query()->findOrFail($workspaceId)->execute(function () use ($operation): mixed {
            return $operation();
        });
    }
}

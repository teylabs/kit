<?php

namespace Tey\Kit\Features\Workspaces;

use Laravel\Pennant\Feature;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Workspaces\Models\Workspace;

final class TenantBoundary
{
    public function run(int $workspaceId, callable $operation): mixed
    {
        $workspace = Workspace::query()->findOrFail($workspaceId);
        try {
            return $workspace->execute(fn () => app(Concierge::class)->context()->run($workspace, \Closure::fromCallable($operation)));
        } finally {
            Feature::flushCache();
        }
    }
}

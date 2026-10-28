<?php

namespace Tey\Kit\Features\Workspaces\Tasks;

use Fiber;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Workspace;

/**
 * Making a workspace the current tenant also makes it Concierge's current workspace (and the permission team); forgetting it restores what was
 * there before. Concierge only offers a scoped `run()`, so the task holds that scope open in a Fiber between makeCurrent and forgetCurrent, and
 * Concierge's own `finally` does the restoring. Spatie forgets the current tenant before making another current, so at most one scope is open.
 */
final class ConciergeContextTask implements SwitchTenantTask
{
    private ?Fiber $scope = null;

    public function makeCurrent(IsTenant $tenant): void
    {
        $this->forgetCurrent();
        if (! $tenant instanceof Workspace) {
            return;
        }
        $scope = new Fiber(fn () => app(Concierge::class)->context()->run($tenant, fn () => Fiber::suspend()));
        $scope->start();
        $this->scope = $scope;
    }

    public function forgetCurrent(): void
    {
        $scope = $this->scope;
        $this->scope = null;
        if ($scope?->isSuspended()) {
            $scope->resume();
        }
    }
}

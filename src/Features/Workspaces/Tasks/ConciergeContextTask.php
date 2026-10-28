<?php

namespace Tey\Kit\Features\Workspaces\Tasks;

use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Workspace;
use Tey\Concierge\Contracts\WorkspaceContext;

/**
 * Making a workspace the current tenant also enters it as Concierge's current workspace (and the permission team); forgetting it leaves, restoring
 * what was there before. Spatie forgets the current tenant before making another current, so at most one entry is open. The context is a scoped
 * binding (renewed between jobs and Octane requests), so the task leaves the instance it entered, never a newer one it did not.
 */
final class ConciergeContextTask implements SwitchTenantTask
{
    private ?WorkspaceContext $entered = null;

    public function makeCurrent(IsTenant $tenant): void
    {
        $this->forgetCurrent();
        if ($tenant instanceof Workspace) {
            $context = app(Concierge::class)->context();
            $context->enter($tenant);
            $this->entered = $context;
        }
    }

    public function forgetCurrent(): void
    {
        $context = $this->entered;
        $this->entered = null;
        $context?->leave();
    }
}

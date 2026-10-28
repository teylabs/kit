<?php

namespace Tey\Kit\Features\Workspaces\Tasks;

use Laravel\Pennant\Feature;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/** Pennant's in-memory results belong to one workspace: they are flushed whenever the current tenant changes, either way. */
final class FlushFeatureCacheTask implements SwitchTenantTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        Feature::flushCache();
    }

    public function forgetCurrent(): void
    {
        Feature::flushCache();
    }
}

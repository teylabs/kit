<?php

namespace Tey\Kit\Features\Workspaces\Tasks;

use Laravel\Pennant\Feature;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;
use Tey\Kit\Features\Workspaces\TenantBoundary;

/**
 * Pennant's in-memory results belong to one workspace: they are flushed whenever the current tenant changes, either way. Not under the `array` store (the kit's default, and
 * tests), where that memory is the store itself and a flush would wipe every flag set in the request; see TenantBoundary::flushes().
 */
final class FlushFeatureCacheTask implements SwitchTenantTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        if (TenantBoundary::flushes()) {
            Feature::flushCache();
        }
    }

    public function forgetCurrent(): void
    {
        if (TenantBoundary::flushes()) {
            Feature::flushCache();
        }
    }
}

<?php

namespace Tey\Kit\Features\Workspaces\Tasks;

use Laravel\Pennant\Feature;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/**
 * Pennant's in-memory results belong to one workspace: they are flushed whenever the current tenant changes, either way. Not under the `array` store (the kit's default, and
 * tests), where that memory is the store itself and a flush would wipe every flag set in the request; see self::flushes().
 */
final class FlushFeatureCacheTask implements SwitchTenantTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        if (self::flushes()) {
            Feature::flushCache();
        }
    }

    public function forgetCurrent(): void
    {
        if (self::flushes()) {
            Feature::flushCache();
        }
    }

    /** Whether switching tenants flushes Pennant's cache: yes for a store with a backend (database, redis), no for the `array` store, which is nothing but that memory. */
    public static function flushes(): bool
    {
        $store = config('pennant.default', 'database');

        return config('pennant.stores.'.$store.'.driver', $store) !== 'array';
    }
}

<?php

namespace Tey\Kit\Features\Workspaces;

use Laravel\Pennant\Feature;
use Tey\Concierge\Concierge;

final class TenantBoundary
{
    public function run(int $workspaceId, callable $operation): mixed
    {
        $workspace = Workspaces::query()->findOrFail($workspaceId);
        try {
            return $workspace->execute(fn () => app(Concierge::class)->context()->run($workspace, \Closure::fromCallable($operation)));
        } finally {
            // Pennant's in-memory cache holds the other tenant's flags; flushed so the next read is this tenant's. The `array` store (the kit's default, and tests) keeps the
            // values themselves in that same memory, so flushing it would wipe every flag set so far in the request: with no backend to go stale against, it is left alone.
            if (self::flushes()) {
                Feature::flushCache();
            }
        }
    }

    /** Whether leaving the boundary flushes Pennant's cache: yes for a store with a backend (database, redis), no for the `array` store, which is nothing but that memory. */
    public static function flushes(): bool
    {
        $store = config('pennant.default', 'database');

        return config('pennant.stores.'.$store.'.driver', $store) !== 'array';
    }
}

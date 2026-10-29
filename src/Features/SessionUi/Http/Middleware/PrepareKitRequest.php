<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tey\Kit\Features\Core\KitShares;
use Tey\Kit\Features\Core\Registry;

/**
 * The registry's request hooks (the brand's local-only `?brand=` switch, and forgetting what the last request remembered), run once the session has
 * started, and the kit's Inertia props put back (KitShares::restore) in case something flushed Inertia's shared props since boot, as
 * pest-plugin-browser does before each request. This runs last in `web`, after the host's own HandleInertiaRequests has merged its shares, and
 * restore() shares only the keys Inertia's factory doesn't already hold, so the host's value for any key wins. It flushes nothing.
 */
final class PrepareKitRequest
{
    public function handle(Request $request, Closure $next): mixed
    {
        KitShares::restore();
        app(Registry::class)->prepareRequest($request);

        return $next($request);
    }
}

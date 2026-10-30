<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tey\Kit\Features\Core\KitShares;
use Tey\Kit\Features\Core\Registry;

/**
 * The registry's late request hooks (the brand's local-only `?brand=` switch), run once the session has started, and the kit's Inertia props put back
 * (KitShares::restore) once more in case a host middleware flushed Inertia's shared props after core's StartKitRequest. This is appended to `web`, after the
 * session and the host's own HandleInertiaRequests; restore() shares only the keys Inertia's factory doesn't already hold, so the host's value for any key
 * wins. The request's memos and the first restore begin earlier, in core's StartKitRequest, first in `web`. It flushes nothing.
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

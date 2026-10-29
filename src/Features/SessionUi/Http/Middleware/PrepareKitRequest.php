<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Registry;

/**
 * The registry's request hooks (the brand's local-only `?brand=` switch, and forgetting what the last request remembered), run once the session has
 * started. It shares no Inertia props and touches no Inertia state: session-ui's props are shared in SessionUiProvider::boot, lazily, so they compose
 * with the host's own HandleInertiaRequests.
 */
final class PrepareKitRequest
{
    public function handle(Request $request, Closure $next): mixed
    {
        app(Registry::class)->prepareRequest($request);

        return $next($request);
    }
}

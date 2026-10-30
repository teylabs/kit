<?php

namespace Tey\Kit\Features\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tey\Kit\Features\Core\KitShares;
use Tey\Kit\Features\Core\Registry;

/**
 * The first thing in `web`: core prepends it to the group and to the middleware priority list on any host that adopts core (with or without
 * session-ui), so it runs before the session, the signed-in person, route-model binding and every middleware of the host's. It begins the request's memos (the registry's start hooks: RequestMemo and the access
 * feature's permission, capability and entitlement memos), so a host's tenant resolver reads through them, and puts back the kit's Inertia props a flush
 * removed (KitShares::restore), so a host share that wraps a kit prop, and an error page rendered before the rest of `web` runs (a route-binding 404),
 * finds them. Neither needs the session, the person or the host's shares: restore() shares only keys Inertia's factory doesn't already hold, so a host's
 * share, before the request or later in its own HandleInertiaRequests, still wins. Each memo belongs to its request (scoped, and keyed by the request),
 * so nothing outlives it. The hooks that need the session run late, in session-ui's PrepareKitRequest. It flushes nothing.
 */
final class StartKitRequest
{
    public function handle(Request $request, Closure $next): mixed
    {
        app(Registry::class)->startRequest($request);
        KitShares::restore();

        return $next($request);
    }
}

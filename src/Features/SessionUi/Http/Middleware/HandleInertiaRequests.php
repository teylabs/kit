<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Inertia;
use Tey\Kit\Features\SessionUi\PageShell;

class HandleInertiaRequests extends Middleware
{
    /** The registry's request hooks run first (the session has started by now: the brand's local-only `?brand=` switch), then Inertia. */
    public function handle(Request $request, \Closure $next)
    {
        app(\Tey\Kit\Features\Core\Registry::class)->prepareRequest($request);

        return parent::handle($request, $next);
    }

    public function share(Request $request): array
    {
        // One share, `shell`: the product and viewer, the workspace switcher, the primary navigation and a small shell-only `can` map. Everything about the page itself
        // (section navigation, heading, document title, breadcrumbs) is the page ViewModel's `layout`, never a second producer. Gretel shares its own `breadcrumbs`, and no
        // page reads it any more: it is cleared here, so there is one source for the trail.
        Inertia::flushShared();

        return [...parent::share($request), 'shell' => fn () => app(PageShell::class)->forRequest($request)->toArray()];
    }
}

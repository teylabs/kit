<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tey\Kit\Features\Access\Permissions;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\SettingsNav;
use Tey\Kit\Features\SessionUi\PageTitle;

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
        // `layout` feeds the folder Layout.vue through the host's Inertia layout callback.
        return [...parent::share($request), 'layout' => fn () => app(PageShell::class)->forRequest($request)->toArray(), 'document_title' => fn () => app(PageTitle::class)->forRequest($request), 'settings_nav' => fn () => app(SettingsNav::class)->forRequest($request), 'can' => fn () => app(Permissions::class)->forRequest($request)];
    }
}

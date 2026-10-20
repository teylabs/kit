<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\PageTitle;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        // `layout` feeds the folder Layout.vue through the host's Inertia layout callback.
        return [...parent::share($request), 'layout' => fn () => app(PageShell::class)->forRequest($request)->toArray(), 'document_title' => fn () => app(PageTitle::class)->forRequest($request)];
    }
}

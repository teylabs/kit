<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tey\Kit\Features\SessionUi\PageShell;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [...parent::share($request), 'shell' => fn () => app(PageShell::class)->forRequest($request)->toArray()];
    }
}

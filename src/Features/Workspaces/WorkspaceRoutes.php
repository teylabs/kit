<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Workspaces\Http\Middleware\ResolveWorkspace;

/** Defines a feature's workspace pages twice: the full form under `/@{workspace}` and the short form without it. */
final class WorkspaceRoutes
{
    /**
     * @param  callable(bool): void  $routes  declares the routes; the argument is true for the full form (name routes only then)
     * @param  list<string>  $middleware  added after the sign-in checks
     */
    public static function define(callable $routes, array $middleware = []): void
    {
        Route::middleware(['auth:web', 'concierge.active', ResolveWorkspace::class, ...$middleware])->group(function () use ($routes): void {
            Route::prefix('@{workspace}')->where(['workspace' => Slugs::PATTERN])->group(fn () => $routes(true));
            $routes(false);
        });
    }
}

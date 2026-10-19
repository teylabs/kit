<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

final class SessionUiProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->errorPages(fn (string $code, int $status, ?string $message, Request $request) => Inertia::render('Error', ['status' => $status, 'message' => $message ?? 'This request is unavailable. Your access or the resource may have changed.', 'recovery_url' => '/workspaces', 'recovery_label' => 'Return to your workspaces'])->toResponse($request)->setStatusCode($status));
    }

    public function boot(): void
    {
        $this->app->make(Router::class)->pushMiddlewareToGroup('web', HandleInertiaRequests::class);
        Fortify::loginView(fn () => Inertia::render('Auth/Login', ['title' => 'Sign in']));
    }
}

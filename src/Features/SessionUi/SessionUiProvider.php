<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Defaults;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\ViewModels\ErrorViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\LoginViewModel;

final class SessionUiProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        // A user with exactly one reachable workspace has nothing to choose on the list: it opens that workspace at its first page.
        $registry->routes(__DIR__.'/routes/sections.php', 'web', $routes);
        $registry->workspaceOpener(function (Request $request): ?string {
            $reachable = array_values(array_filter(app(PageShell::class)->forRequest($request)->switcher['choices'], fn (array $choice) => $choice['url'] !== null));

            return count($reachable) === 1 ? $reachable[0]['url'] : null;
        });
        $registry->errorPages(fn (string $code, int $status, ?string $message, Request $request) => Inertia::render('Workspace/Error/Page', new ErrorViewModel($status.' · Request could not be completed', $status, $message ?? 'This request is unavailable. Your access or the resource may have changed.', '/me/workspaces', 'Return to your workspaces'))->toResponse($request)->setStatusCode($status));
    }

    public function register(): void
    {
        Defaults::apply($this->app, 'fortify', 'laravel/fortify');
        Defaults::apply($this->app, 'inertia', 'inertiajs/inertia-laravel');
    }

    public function boot(): void
    {
        $this->app->make(Router::class)->pushMiddlewareToGroup('web', HandleInertiaRequests::class);
        Fortify::loginView(fn () => Inertia::render('Auth/Login/Page', new LoginViewModel('Sign in')));
    }
}

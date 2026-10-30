<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;
use Tey\Kit\Features\Core\HomeRoute;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\PlaceholderController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

/**
 * Declares the routes of the registered placeholders: each at the real page's name and address, in both URL forms, with the real page's breadcrumb. A placeholder that
 * shares its route name with a real route (the page was built and the `placeholder()` line forgotten) is a failure, not a silent override. One per application (a container
 * singleton, like the Registry), so it remembers what it declared for that application only.
 */
final class PlaceholderRoutes
{
    /** @var array<string, true> */
    private array $declared = [];

    public function __construct(private readonly Application $app) {}

    public function register(): void
    {
        $router = $this->app->make('router');
        $pending = array_filter($this->app->make(Registry::class)->placeholders(), fn (array $placeholder): bool => ! isset($this->declared[$placeholder['route']]));
        if ($pending === []) {
            return;
        }
        foreach ($pending as $placeholder) {
            $this->declared[$placeholder['route']] = true;
            if ($router->has($placeholder['route']) || $router->has('short.'.$placeholder['route'])) {
                throw new RuntimeException('Placeholder shares its route name with a registered route; delete the placeholder() line: '.$placeholder['route']);
            }
        }
        $declare = fn () => WorkspaceRoutes::define(function (bool $full) use ($pending, $router): void {
            foreach ($pending as $placeholder) {
                $route = $router->get($placeholder['uri'], PlaceholderController::class)->defaults('placeholder', $placeholder['route']);
                WorkspaceRoutes::page($route, $full, 'session-ui', $placeholder['route'], end($placeholder['breadcrumbs']) ?: $placeholder['title'], $placeholder['settings'] ? ['session-ui', 'workspaces.settings.index'] : HomeRoute::parent());
            }
        });
        // Called while the kit's route file loads, the `web` group is already around it; declared later (a test, an app's own boot) it needs its own.
        $inWeb = collect($router->getGroupStack())->flatMap(fn (array $group): array => (array) ($group['middleware'] ?? []))->contains('web');
        $inWeb ? $declare() : $router->middleware('web')->group($declare);
    }
}

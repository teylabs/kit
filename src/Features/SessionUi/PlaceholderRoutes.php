<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\PlaceholderController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

/**
 * Declares the routes of the registered placeholders: each at the real page's name and address, in both URL forms, with the real page's breadcrumb. A placeholder that
 * shares its route name with a real route (the page was built and the `placeholder()` line forgotten) is a failure, not a silent override.
 */
final class PlaceholderRoutes
{
    /** @var array<string, true> */
    private static array $declared = [];

    public static function register(): void
    {
        $registry = app(Registry::class);
        $pending = array_filter($registry->placeholders(), fn (array $placeholder): bool => ! isset(self::$declared[$placeholder['route']]));
        if ($pending === []) {
            return;
        }
        foreach ($pending as $placeholder) {
            self::$declared[$placeholder['route']] = true;
            if (Route::has($placeholder['route']) || Route::has('short.'.$placeholder['route'])) {
                throw new RuntimeException('Placeholder shares its route name with a registered route; delete the placeholder() line: '.$placeholder['route']);
            }
        }
        $declare = fn () => WorkspaceRoutes::define(function (bool $full) use ($pending): void {
            foreach ($pending as $placeholder) {
                $route = Route::get($placeholder['uri'], PlaceholderController::class)->defaults('placeholder', $placeholder['route']);
                WorkspaceRoutes::page($route, $full, 'session-ui', $placeholder['route'], end($placeholder['breadcrumbs']) ?: $placeholder['title'], $placeholder['settings'] ? ['session-ui', 'workspaces.settings.index'] : ['session-ui', 'home']);
            }
        });
        // Called while the kit's route file loads, the `web` group is already around it; declared later (a test, an app's own boot) it needs its own.
        $inWeb = collect(Route::getGroupStack())->flatMap(fn (array $group): array => (array) ($group['middleware'] ?? []))->contains('web');
        $inWeb ? $declare() : Route::middleware('web')->group($declare);
    }

    /** Forgets what was declared: for tests that rebuild the application. */
    public static function reset(): void
    {
        self::$declared = [];
    }
}

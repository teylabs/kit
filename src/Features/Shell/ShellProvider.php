<?php

namespace Tey\Kit\Features\Shell;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;

/**
 * The shell: the generated `pages/Workspace/Layout.vue` and its `Partials/`, rendering a server-built ShellData. It needs only core and
 * contributes no routes, users, memberships or workspaces. It shares the `shell` prop every page reads (the PublicShell's footer and CTA too),
 * built by the bound ShellSource: PlainShell here, session-ui's PageShell when that is adopted. session-ui's middleware keeps this share
 * across its flush, so the prop has one producer whichever features are adopted.
 */
final class ShellProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void {}

    public function register(): void
    {
        $this->app->bindIf(ShellSource::class, PlainShell::class);
    }

    public function boot(): void
    {
        if (class_exists(Inertia::class)) {
            Inertia::share('shell', fn (): array => app(ShellSource::class)->forRequest(request())->toArray());
        }
    }
}

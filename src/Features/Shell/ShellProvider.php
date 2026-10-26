<?php

namespace Tey\Kit\Features\Shell;

use Illuminate\Support\ServiceProvider;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;

/**
 * The shell: the generated `pages/Workspace/Layout.vue` and its `Partials/`, rendering a server-built ShellData. It needs only core and
 * contributes nothing at runtime: no routes, users, memberships or workspaces. The host (or session-ui's PageShell) builds the ShellData.
 */
final class ShellProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void {}
}

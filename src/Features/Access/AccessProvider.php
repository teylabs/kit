<?php

namespace Tey\Kit\Features\Access;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Access\Policies\WorkspacePolicy;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Defaults;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Models\Workspace;

final class AccessProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->errors(['capability_unavailable' => 403, 'entitlement_required' => 403]);
    }

    public function register(): void
    {
        Defaults::apply($this->app, 'pennant', 'laravel/pennant');
        // One instance per request, so its gate results are memoized for the request and no longer: the shell, the page's layout and the page share them.
        $this->app->scoped(Permissions::class);
    }

    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        $this->commands([SyncPermissionsCommand::class]);
        foreach (app(Registry::class)->workspaceCapabilities() as $capability) {
            Feature::define($capability, fn (Workspace $workspace): bool => (bool) (config('kit.capabilities', [])[$capability] ?? false));
        }
    }
}

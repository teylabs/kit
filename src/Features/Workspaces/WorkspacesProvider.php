<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Tey\Concierge\Concierge;
use Spatie\Multitenancy\Contracts\IsTenant;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Defaults;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Operations\ProvisionWorkspace;

final class WorkspacesProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->routes(__DIR__.'/routes/workspaces.php', 'web', $routes)
            ->permission('workspace.members.manage', ['owner', 'admin'])
            ->settingsSection('personal', 'workspaces', 'Workspaces', 'The workspaces you belong to.', group: 'Workspaces');
    }

    public function register(): void
    {
        Defaults::apply($this->app, 'concierge', 'tey/concierge');
        Defaults::apply($this->app, 'multitenancy', 'spatie/laravel-multitenancy');
        Defaults::apply($this->app, 'permission', 'spatie/laravel-permission');
        // The multitenancy provider may have bound its tenant model before these defaults applied.
        $this->app->bind(IsTenant::class, config('multitenancy.tenant_model'));
    }

    public function boot(): void
    {
        app(Concierge::class)->provisionWorkspaceUsing(fn ($owner, $invitation) => app(ProvisionWorkspace::class)->handle($owner, $invitation));
        Gate::define('concierge.manage-tenants', fn (Authenticatable $user): bool => Users::is($user) && $user->conciergeHasVerifiedEmail() && app(Concierge::class)->account($user)->isActive() && DB::table('operators')->where('actor_id', $user->id)->exists());
    }
}

<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Operations\ProvisionWorkspace;

final class WorkspacesProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->routes(__DIR__.'/routes/workspaces.php', 'web', $routes);
    }

    public function boot(): void
    {
        app(Concierge::class)->provisionWorkspaceUsing(fn ($owner, $invitation) => app(ProvisionWorkspace::class)->handle($owner, $invitation));
        Gate::define('concierge.manage-tenants', fn (Authenticatable $user): bool => Users::is($user) && $user->conciergeHasVerifiedEmail() && app(Concierge::class)->account($user)->isActive() && DB::table('operators')->where('actor_id', $user->id)->exists());
    }
}

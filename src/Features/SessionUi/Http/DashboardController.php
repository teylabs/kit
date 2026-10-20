<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\ViewModels\DashboardViewModel;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** A workspace's home: where sign-in lands. An empty state until the app registers its own dashboard component. */
class DashboardController
{
    public function show(Request $request, int $workspace): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace): mixed {
            $user = $request->user();
            abort_unless(Users::is($user), 401);
            app(Access::class)->authorize(new Principal((int) $user->getKey()), 'dashboard.read');
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['workspace' => ['id' => $workspace, 'name' => Workspace::required()->name]]);
            }
            $component = app(Registry::class)->dashboardComponent();

            return Inertia::render($component ?? 'Workspace/Dashboard/Page', new DashboardViewModel('Dashboard', (string) Workspace::required()->name, 'Open settings', WorkspaceUrl::to($workspace, 'settings', 'session-ui', $request)));
        });
    }
}

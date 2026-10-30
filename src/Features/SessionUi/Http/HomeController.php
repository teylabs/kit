<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * The kit's home (`kit.home`, "/" by default): where sign-in, the home crumb and the kit's own links land. For a user with one workspace (the short form)
 * it IS that workspace's dashboard. A user with several goes to the workspace they last used (the session's `kit.workspace`, kept by every workspace page
 * they open), while they are still an active member of it and may open its dashboard; else, as a guest does, to the workspace list (a guest signs in
 * first). A stale workspace (removed, suspended, not admitted, deleted) is never named: it falls back to the list. An app can serve "/" itself.
 */
class HomeController
{
    public function __invoke(Request $request): mixed
    {
        $user = $request->user('web');
        if ($user !== null && WorkspaceUrl::short($request) && ($workspace = WorkspaceUrl::currentMemberId($request)) !== null) {
            // The shell reads the workspace from the route, as on every other workspace page.
            $request->route()?->setParameter('workspace', (string) $workspace);
            $request->session()->put('kit.workspace', $workspace);

            return app(DashboardController::class)->show($request, $workspace);
        }
        // currentMemberId only names a workspace the person is an active member of; the dashboard's own check decides the rest (account, verified email,
        // admission, the dashboard ability), so the redirect never lands on a refusal.
        if ($user !== null && ($workspace = WorkspaceUrl::currentMemberId($request)) !== null && $this->opens($user, $workspace)) {
            return redirect(WorkspaceUrl::to($workspace, '', null, $request));
        }

        return redirect('/me/workspaces');
    }

    /** Whether the person may open the workspace's dashboard: the same check DashboardController makes. Any refusal is a no, except the dashboard turned off: then its root opens another page. */
    private function opens(object $user, int $workspace): bool
    {
        try {
            return app(TenantBoundary::class)->run($workspace, fn (): bool => (bool) app(Access::class)->authorize(new Principal((int) $user->getKey()), 'dashboard.read'));
        } catch (\RuntimeException $refused) {
            // The dashboard turned off is not a refusal of the workspace: its root opens the first page the person may open (DashboardController).
            return $refused->getMessage() === 'capability_unavailable';
        }
    }
}

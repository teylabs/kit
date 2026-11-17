<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Routing\PublicRoute;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Tey\Kit\Features\WorkspaceUi\HomeDestination;

/**
 * The kit's home (`kit.home`, "/" by default): where sign-in, the home crumb and the kit's own links land. For a user with one workspace (the short form)
 * it IS that workspace's dashboard. A user with several goes to the workspace they last used (the session's `kit.workspace`, kept by every workspace page
 * they open), while they are still an active member of it and may open its dashboard; else, as a guest does, to the workspace list (a guest signs in
 * first). A stale workspace (removed, suspended, not admitted, deleted) is never named: it falls back to the list. An app can serve "/" itself.
 */
#[PublicRoute('The home page: guests see the welcome page, signed-in users are sent on', open: true)]
class HomeController
{
    public function __invoke(Request $request): mixed
    {
        $user = $request->user('web');
        if ($user !== null && WorkspaceUrl::short($request) && ($workspace = WorkspaceUrl::currentMemberId($request)) !== null) {
            // The shell reads the workspace from the route, as on every other workspace page.
            $request->route()?->setParameter('workspace', (string) $workspace);
            $request->session()->put('kit.workspace', $workspace);

            // A personal route, so ResolveWorkspace has not made the workspace current: the dashboard is built, and its Inertia response
            // rendered (closure, optional and deferred props included), inside it here.
            return Workspaces::run($workspace, function () use ($request, $workspace) {
                $response = app(DashboardController::class)->show($request, $workspace);

                return $response instanceof Responsable ? $response->toResponse($request) : $response;
            });
        }

        // Else straight to the person's home (HomeDestination: the last-used workspace they may open, else the list's one reachable workspace), never
        // through the list first; anything uncertain is the list.
        return redirect($user !== null ? HomeDestination::resolve($request) ?? '/me/workspaces' : '/me/workspaces');
    }
}

<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * The root of the app. For a user with one workspace (the short form) "/" IS that workspace's dashboard; anyone else goes to
 * the workspace list (a guest signs in first). An app can serve "/" itself.
 */
class HomeController
{
    public function __invoke(Request $request): mixed
    {
        if ($request->user('web') !== null && WorkspaceUrl::short($request) && ($workspace = WorkspaceUrl::currentMemberId($request)) !== null) {
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

        return redirect('/me/workspaces');
    }
}

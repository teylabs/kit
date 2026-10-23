<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * The root of the app. For a user with one workspace (the short form) "/" IS that workspace's dashboard; anyone else goes to
 * the workspace list (a guest signs in first). An app can serve "/" itself.
 */
class HomeController
{
    public function __invoke(Request $request): mixed
    {
        if ($request->user('web') !== null && WorkspaceUrl::short($request) && ($workspace = WorkspaceUrl::currentId($request)) !== null) {
            // The shell reads the workspace from the route, as on every other workspace page.
            $request->route()?->setParameter('workspace', (string) $workspace);
            $request->session()->put('kit.workspace', $workspace);

            return app(DashboardController::class)->show($request, $workspace);
        }

        return redirect('/me/workspaces');
    }
}

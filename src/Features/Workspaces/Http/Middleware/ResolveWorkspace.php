<?php

namespace Tey\Kit\Features\Workspaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * Turns the route's `{workspace}` slug into the workspace id the controllers take. A route without the slug is the
 * short form: it means the session's current workspace, else the user's only one, else the workspace list. Whether
 * the user may use the workspace is still decided by each operation.
 */
class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): mixed
    {
        $route = $request->route();
        $slug = $route->parameter('workspace');
        if ($slug === null) {
            $workspace = $this->current($request);
            if ($workspace === null) {
                return redirect('/me/workspaces');
            }
        } else {
            $workspace = Workspace::query()->where('slug', $slug)->first() ?? throw new RuntimeException('workspace_not_found');
        }
        // Controllers take their route parameters in order, workspace first, which a short path's parameters lack.
        $parameters = $route->parameters();
        unset($parameters['workspace']);
        $route->parameters = ['workspace' => (string) $workspace->getKey()] + $parameters;
        if ($request->user('web') !== null && app(Concierge::class)->membership($request->user('web'), $workspace)?->isActive()) {
            $request->session()->put('kit.workspace', $workspace->getKey());
        }

        return $next($request);
    }

    private function current(Request $request): ?Workspace
    {
        $user = $request->user('web');
        $remembered = $request->session()->get('kit.workspace');
        if ($remembered !== null && ($workspace = Workspace::query()->find($remembered)) !== null && app(Concierge::class)->membership($user, $workspace)?->isActive()) {
            return $workspace;
        }
        $memberships = WorkspaceUrl::memberships($user);

        return count($memberships) === 1 ? $memberships[0] : null;
    }
}

<?php

namespace Tey\Kit\Features\Workspaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * Turns the route's `{workspace}` slug into the workspace id the controllers take. A route without the slug is the
 * short form: it means the session's current workspace, else the user's only one, else the workspace list. Whether
 * the user may use the workspace is still decided by each operation.
 *
 * A workspace being deleted (closed, `deleting_at`) opens no page: its members are sent to the workspace list with a notice that the deletion is in
 * progress (a JSON request is refused `workspace_deleting`); anyone else finds it absent, as before.
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
                return redirect(WorkspaceUrl::chooser());
            }
        } else {
            $workspace = Workspaces::query()->where('slug', $slug)->first() ?? throw new RuntimeException('workspace_not_found');
        }
        if ($workspace->isDeleting()) {
            $user = $request->user('web');
            if (! $user instanceof Member || ! RequestMemo::membership($user, $workspace)?->isActive()) {
                throw new RuntimeException('workspace_not_found');
            }
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                throw new RuntimeException('workspace_deleting');
            }
            $request->session()->forget('kit.workspace');
            Toast::info((string) $workspace->name.' is being deleted.', 'Deletion is in progress. Its pages are closed.');

            return redirect(WorkspaceUrl::chooser());
        }
        // Controllers take their route parameters in order, workspace first, which a short path's parameters lack.
        $parameters = $route->parameters();
        unset($parameters['workspace']);
        $route->parameters = ['workspace' => (string) $workspace->getKey()] + $parameters;
        if ($request->user('web') !== null && RequestMemo::membership($request->user('web'), $workspace)?->isActive()) {
            $request->session()->put('kit.workspace', $workspace->getKey());
        }

        return $next($request);
    }

    private function current(Request $request): ?Workspace
    {
        $user = $request->user('web');
        $remembered = $request->session()->get('kit.workspace');
        if ($remembered !== null && ($workspace = Workspaces::query()->find($remembered)) !== null && ! $workspace->isDeleting() && RequestMemo::membership($user, $workspace)?->isActive()) {
            return $workspace;
        }
        $memberships = WorkspaceUrl::memberships($user);

        return count($memberships) === 1 ? $memberships[0] : null;
    }
}

<?php

namespace Tey\Kit\Features\Workspaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ReflectionProperty;
use RuntimeException;
use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * Turns the route's `{workspace}` slug into the workspace id the controllers take. A route without the slug is the
 * short form: it means the session's current workspace, else the user's only one, else the workspace list. Someone
 * who is not an active member gets `workspace_not_found` (a 404) before anything runs in the workspace. Otherwise the
 * workspace is the current tenant for the rest of the request (the switch tasks set Concierge's context and flush
 * Pennant), and the tenant that was current before comes back afterwards, even on failure. The response, with its
 * Inertia props, is built inside; terminating callbacks the request registers run inside it too, and the tenant is restored
 * after them even when one throws. What each person may do in the workspace is still decided by each operation: an active member without the permission gets a 403.
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
        // Controllers take their route parameters in order, workspace first, which a short path's parameters lack.
        $parameters = $route->parameters();
        unset($parameters['workspace']);
        $route->parameters = ['workspace' => (string) $workspace->getKey()] + $parameters;
        $user = $request->user('web');
        if ($user === null || ! RequestMemo::membership($user, $workspace)?->isActive()) {
            throw new RuntimeException('workspace_not_found');
        }
        $request->session()->put('kit.workspace', $workspace->getKey());
        // Terminating callbacks run after the response is sent, once the tenant is restored. The ones the request registers are
        // taken out and run together inside the workspace, and what was current before comes back even when one of them throws.
        $app = app();
        $callbacks = new ReflectionProperty($app, 'terminatingCallbacks');
        $start = count($callbacks->getValue($app));
        try {
            return $workspace->execute(function () use ($request, $next): mixed {
                return $next($request);
            });
        } finally {
            $all = $callbacks->getValue($app);
            $registered = array_slice($all, $start);
            if ($registered !== []) {
                $callbacks->setValue($app, array_slice($all, 0, $start));
                $app->terminating(function () use ($app, $workspace, $registered): void {
                    $previous = $workspace::current();
                    $workspace->makeCurrent();
                    try {
                        foreach ($registered as $callback) {
                            $app->call($callback);
                        }
                    } finally {
                        $previous === null ? $workspace::forgetCurrent() : $previous->makeCurrent();
                    }
                });
            }
        }
    }

    private function current(Request $request): ?Workspace
    {
        $user = $request->user('web');
        $remembered = $request->session()->get('kit.workspace');
        if ($remembered !== null && ($workspace = Workspaces::query()->find($remembered)) !== null && RequestMemo::membership($user, $workspace)?->isActive()) {
            return $workspace;
        }
        $memberships = WorkspaceUrl::memberships($user);

        return count($memberships) === 1 ? $memberships[0] : null;
    }
}

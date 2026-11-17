<?php

namespace Tey\Kit\Features\WorkspaceUi;

use Illuminate\Http\Request;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Throwable;

/**
 * Where a signed-in person's home is, decided once: the rule the kit's home ("/"), the workspace list (`/me/workspaces`) and sign-in all follow, so
 * none of them sends the person through another to get there. In order:
 *
 * 1. One workspace (the short form): `kit.home` itself, which IS that workspace's dashboard.
 * 2. The workspace they last used (the session's `kit.workspace`), while they are an active member and may open its dashboard (account, verified email,
 *    admission, `dashboard.read`; the dashboard turned off still opens its root, which opens another page).
 * 3. The one workspace they can reach from the list (the switcher's landings, each decided in its own workspace): that landing.
 * 4. Otherwise null: the workspace list. So are no workspaces, several without a pick, a guest, and any failure: it fails closed, never to a workspace.
 */
final class HomeDestination
{
    public static function resolve(Request $request): ?string
    {
        if ($request->user('web') === null) {
            return null;
        }
        try {
            // Sign-in may have read the short form before anyone was signed in: decided again for the person now signed in.
            $request->attributes->remove('kit.short_urls');
            $workspace = WorkspaceUrl::currentMemberId($request);
            if ($workspace !== null && WorkspaceUrl::short($request)) {
                return (string) config('kit.home', '/');
            }
            if ($workspace !== null && self::opens($request->user('web'), $workspace)) {
                return WorkspaceUrl::to($workspace, '', null, $request);
            }

            return self::reachable($request);
        } catch (Throwable) {
            return null;
        }
    }

    /** The list's own pick (workspace-ui's workspace opener, which the list also follows): its one reachable workspace, or null. */
    public static function reachable(Request $request): ?string
    {
        return app(Registry::class)->opensWorkspace($request);
    }

    /** Whether the person may open the workspace's dashboard: the same check DashboardController makes. Any refusal is a no, except the dashboard turned off: then its root opens another page. */
    private static function opens(object $user, int $workspace): bool
    {
        try {
            return Workspaces::run($workspace, function () use ($user): bool {
                return (bool) app(Access::class)->authorize(new Principal((int) $user->getKey()), 'dashboard.read');
            });
        } catch (\RuntimeException $refused) {
            return $refused->getMessage() === 'capability_unavailable';
        }
    }
}

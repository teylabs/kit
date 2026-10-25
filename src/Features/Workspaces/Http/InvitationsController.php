<?php

namespace Tey\Kit\Features\Workspaces\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** A user's invitations (`/me/invitations`): creating one accepts a workspace invitation, by its token. */
class InvitationsController
{
    /** Where an accepted invitation lands: `kit.invitation_redirect` (a full route name taking `workspace`), else the first available primary navigation page, else the workspace list. */
    private function landing(Request $request, int $workspace): string
    {
        $route = config('kit.invitation_redirect');
        if (is_string($route) && $route !== '') {
            return route($route, ['workspace' => $workspace]);
        }
        foreach (array_filter(app(Registry::class)->navigationLinks(), fn (array $link) => $link['slot'] === 'primary') as $link) {
            $route = config('kit.routes.'.$link['suffix'].'.name', '').'workspaces.'.$link['suffix'].'.index';
            if (Route::has($route)) {
                return url(WorkspaceUrl::to($workspace, $link['suffix'], $link['suffix'], $request));
            }
        }

        return route(config('kit.routes.workspaces.name', '').'workspaces.index');
    }

    public function store(Request $request): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        $result = AcceptWorkspaceInvitation::run(new Principal((int) $user->getKey()), $data['token']);

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($result) : redirect($this->landing($request, (int) $result['workspace_id']));
    }
}

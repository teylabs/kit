<?php

namespace Tey\Kit\Features\Workspaces\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\FirstPage;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;

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
        if (($first = FirstPage::url($workspace, $request)) !== null) {
            return $first;
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

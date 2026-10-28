<?php

namespace Tey\Kit\Features\Workspaces\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tey\Concierge\Actions\LeaveWorkspace;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Tey\Kit\Features\Workspaces\Workspaces;

/** Your own membership of a workspace: ending it is leaving (Concierge's `LeaveWorkspace`). The owner transfers ownership first; after leaving, access is gone. */
class MembershipController
{
    public function destroy(Request $request, string $workspace): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $found = Workspaces::query()->where('slug', $workspace)->first() ?? abort(404);

        try {
            LeaveWorkspace::run($user, $found);
        } catch (AuthorizationException $denied) {
            // Concierge's reasons, in words the page can show: the owner must transfer first, and someone who is not a member has nothing to leave.
            abort_if($denied->getCode() === 'not_member', 404);
            throw ValidationException::withMessages(['leave' => $denied->getCode() === 'owner_must_transfer' ? 'You are the owner of this workspace. Transfer ownership to another member before you leave.' : $denied->getMessage()]);
        }

        return $request->expectsJson() && ! $request->header('X-Inertia') ? response()->json(['left' => true, 'redirect' => WorkspaceUrl::chooser()]) : redirect(WorkspaceUrl::chooser(), 303);
    }
}

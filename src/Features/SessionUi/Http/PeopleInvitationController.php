<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** The workspace's open invitations: invite by email with a role, resend one, revoke one. */
class PeopleInvitationController
{
    public function store(Request $request, int $workspace): mixed
    {
        $data = $request->validate(['email' => ['required', 'string', 'max:254'], 'role' => ['required', 'string', 'max:64'], 'message' => ['nullable', 'string', 'max:500']]);
        $issued = app(TenantBoundary::class)->run($workspace, fn () => ManagePeople::make()->invite($this->actor($request), $data['email'], $data['role'], $data['message'] ?? null));
        $invitation = $issued->invitation;

        $link = url('/invitations/'.$issued->token);

        return $request->expectsJson() ? response()->json(['id' => (string) $invitation->public_id, 'email' => $invitation->email, 'link' => $link], 201) : $this->toPeople($request, $workspace)->with('invite_link', $link);
    }

    /** Resend: issues the invitation again (a fresh link; Concierge applies its cooldown). */
    public function update(Request $request, int $workspace, string $invitation): mixed
    {
        $issued = app(TenantBoundary::class)->run($workspace, fn () => ManagePeople::make()->resend($this->actor($request), $invitation));
        $link = url('/invitations/'.$issued->token);

        return $request->expectsJson() ? response()->json(['id' => (string) $issued->invitation->public_id, 'resent' => true, 'link' => $link]) : back()->with('invite_link', $link);
    }

    public function destroy(Request $request, int $workspace, string $invitation): mixed
    {
        $result = app(TenantBoundary::class)->run($workspace, fn () => ManagePeople::make()->revoke($this->actor($request), $invitation));

        if ($request->expectsJson()) {
            return response()->json(['id' => (string) $result->public_id, 'revoked' => true]);
        }
        // Plain, with no trigger to pulse: the row it came from is gone.
        Toast::success('Invitation revoked.')->style('plain');

        return back();
    }

    private function actor(Request $request): Model
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return $user;
    }

    /** The People list, named outright: the invitation was sent from a modal over it, and the modal's own base-URL header cannot be relied on to take the redirect there. */
    private function toPeople(Request $request, int $workspace): RedirectResponse
    {
        return redirect(WorkspaceUrl::to(Workspace::query()->findOrFail($workspace), 'settings/people', 'session-ui', $request));
    }
}

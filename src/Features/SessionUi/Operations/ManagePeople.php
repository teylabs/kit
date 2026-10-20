<?php

namespace Tey\Kit\Features\SessionUi\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsAction;
use Tey\Concierge\Actions\ChangeMemberRoles;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Actions\RemoveMembership;
use Tey\Concierge\Actions\ResendInvitation;
use Tey\Concierge\Actions\RevokeInvitation;
use Tey\Concierge\Models\Invitation;
use Tey\Concierge\Models\Membership;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Concierge\Support\IssuedInvitation;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/**
 * The workspace's people, through Concierge: the kit decides who may open the controls (the `workspace.members.manage` permission, by policy) and
 * Concierge enforces the rules on the server (the owner cannot be managed, nobody manages themselves or an equal or higher rank, and only the roles
 * `grantable_by` allows can be granted), so a request that skips the UI is refused the same way. Runs inside the workspace's tenant context.
 */
class ManagePeople
{
    use AsAction;

    public function invite(Model $actor, string $email, string $role): IssuedInvitation
    {
        $this->authorize($actor);

        return InviteMember::run($actor, Workspace::required(), $email, new GrantSet([$role]));
    }

    public function changeRole(Model $actor, int $membership, string $role): Membership
    {
        $this->authorize($actor);

        return ChangeMemberRoles::run($actor, $this->membership($membership), new GrantSet([$role]));
    }

    /**
     * The owner passes ownership to another active, verified member, who becomes the owner while the old owner becomes an admin (Concierge's
     * `transfer_demotes_to`). Only the owner may, and only after the password is confirmed again: the password here is checked, then marked as just
     * confirmed for Concierge's own recent-authentication check, which stays authoritative and is cleared straight after.
     */
    public function transferOwnership(Model $actor, int $membership, string $password): Membership
    {
        $this->authorize($actor);
        if (! \Illuminate\Support\Facades\Hash::check($password, (string) $actor->getAuthPassword())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['password' => 'That password is not right.']);
        }
        $session = app('session.store');
        $session->put('auth.password_confirmed_at', time());
        try {
            return \Tey\Concierge\Actions\TransferOwnership::run($actor, $this->membership($membership));
        } finally {
            $session->forget('auth.password_confirmed_at');
        }
    }

    public function remove(Model $actor, int $membership): Membership
    {
        $this->authorize($actor);

        return RemoveMembership::run($actor, $this->membership($membership));
    }

    public function resend(Model $actor, string $invitation): IssuedInvitation
    {
        $this->authorize($actor);

        return ResendInvitation::run($actor, $this->invitation($invitation));
    }

    public function revoke(Model $actor, string $invitation): Invitation
    {
        $this->authorize($actor);

        return RevokeInvitation::run($actor, $this->invitation($invitation));
    }

    private function authorize(Model $actor): void
    {
        Gate::forUser($actor)->authorize('manageMembers', Workspace::required());
    }

    private function membership(int $id): Membership
    {
        return ConciergeModels::membership()::query()->where('workspace_id', Workspace::required()->getKey())->findOrFail($id);
    }

    private function invitation(string $publicId): Invitation
    {
        return ConciergeModels::invitation()::query()->where('workspace_id', Workspace::required()->getKey())->where('public_id', $publicId)->firstOrFail();
    }
}

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

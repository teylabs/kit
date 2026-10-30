<?php

namespace Tey\Kit\Features\SessionUi\Operations;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Tey\Concierge\Actions\ChangeMemberRoles;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Actions\RemoveMembership;
use Tey\Concierge\Actions\ResendInvitation;
use Tey\Concierge\Actions\RevokeInvitation;
use Tey\Concierge\Actions\TransferOwnership;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Models\Invitation;
use Tey\Concierge\Models\Membership;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Concierge\Support\IssuedInvitation;
use Tey\Kit\Features\SessionUi\HiddenGrants;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * The workspace's people, through Concierge: the kit decides who may open the controls (the `workspace.members.manage` permission, by policy) and
 * Concierge enforces the rules on the server (the owner cannot be managed, nobody manages themselves or an equal or higher rank, and only the roles
 * `grantable_by` allows can be granted), so a request that skips the UI is refused the same way. Runs inside the workspace's tenant context.
 */
class ManagePeople
{
    use AsAction;

    public function invite(Model $actor, string $email, string $role, ?string $message = null): IssuedInvitation
    {
        $this->authorize($actor);
        self::memberRole($role);

        return InviteMember::run($actor, Workspaces::required(), $email, new GrantSet([$role]), $message);
    }

    public function changeRole(Model $actor, int $membership, string $role): Membership
    {
        $this->authorize($actor);
        self::memberRole($role);

        return ChangeMemberRoles::run($actor, $this->membership($membership), new GrantSet([$role]));
    }

    /**
     * The owner passes ownership to another active, verified member, who becomes the owner while the old owner becomes an admin (Concierge's
     * `transfer_demotes_to`). Only the owner may, and only after the password is confirmed again: the password here is checked, then marked as just
     * confirmed for Concierge's own recent-authentication check, which stays authoritative and is cleared straight after. The workspace's name is typed to
     * confirm, and checked here. A scoped guest (Concierge 0.9's guest-only membership) is never a recipient, refused here as well as by Concierge 0.9.
     */
    public function transferOwnership(Model $actor, int $membership, string $password, string $confirmation): Membership
    {
        self::memberOrAbsent($actor);
        $this->authorize($actor);
        $this->authorizeOwner($actor);
        $recipient = $this->membership($membership);
        if (! self::transferable($recipient)) {
            Response::deny('Ownership can only pass to another active, verified member.', 'recipient_invalid')->authorize();
        }
        if (trim($confirmation) !== (string) Workspaces::required()->name) {
            throw ValidationException::withMessages(['confirmation' => 'Type the workspace name exactly to confirm.']);
        }
        if (! Hash::check($password, (string) $actor->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => 'That password is not right.']);
        }
        $session = app('session.store');
        $session->put('auth.password_confirmed_at', time());
        try {
            return TransferOwnership::run($actor, $recipient);
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

    /** Whether a membership may be offered (and accepted) as the new owner: active, verified, not the owner, and never a scoped guest. */
    public static function transferable(Membership $membership): bool
    {
        $concierge = app(Concierge::class);
        // Concierge 0.9 adds scoped guests; on 0.8 there are none, and the method is absent.
        if (method_exists($concierge, 'isScopedGuest') && $concierge->isScopedGuest($membership)) {
            return false;
        }

        return $membership->status === MembershipStatus::Active && ! $concierge->isOwner($membership) && (bool) $membership->user?->conciergeHasVerifiedEmail();
    }

    /** Someone who is not an active member of the workspace finds nothing there: a 404, before any permission is asked about. */
    public static function memberOrAbsent(Model $actor): void
    {
        abort_unless(Gate::forUser($actor)->allows('view', Workspaces::required()), 404);
    }

    /** The workspace's owner only: an admin who may manage members is refused (403) before anything else is checked. */
    public function authorizeOwner(Model $actor): void
    {
        $own = $actor instanceof Member ? app(Concierge::class)->membership($actor, Workspaces::required()) : null;
        if ($own === null || $own->status !== MembershipStatus::Active || ! app(Concierge::class)->isOwner($own)) {
            Response::deny('Only the workspace owner can transfer ownership.', 'not_owner')->authorize();
        }
    }

    /**
     * The roles People offers: workspace member roles only. A scoped role (Concierge's `'scoped' => true`, such as guest) is granted on one record, never
     * workspace-wide, so it is never offered here.
     *
     * @param  list<string>  $roles
     * @return list<string>
     */
    public static function memberRoles(array $roles): array
    {
        return array_values(array_filter($roles, fn (string $role): bool => ! self::scoped($role)));
    }

    /** Fails closed: a posted scoped role is refused, whatever the actor may otherwise grant. */
    public static function memberRole(string $role): void
    {
        if (self::scoped($role)) {
            Response::deny('Choose a member role. Scoped access is granted on a record, not to the workspace.', 'role_not_grantable')->authorize();
        }
    }

    private static function scoped(string $role): bool
    {
        return config('concierge.roles.'.$role.'.scoped', false) === true;
    }

    private function authorize(Model $actor): void
    {
        Gate::forUser($actor)->authorize('manageMembers', Workspaces::required());
    }

    private function membership(int $id): Membership
    {
        // A member hidden by kit.people.hide_grants can't be changed, removed or given ownership by id: a 404, as for no such member.
        return ConciergeModels::membership()::query()->where('workspace_id', Workspaces::required()->getKey())->tap(fn ($query) => HiddenGrants::scope($query, Workspaces::required()->getKey()))->findOrFail($id);
    }

    private function invitation(string $publicId): Invitation
    {
        return ConciergeModels::invitation()::query()->where('workspace_id', Workspaces::required()->getKey())->where('public_id', $publicId)->firstOrFail();
    }
}

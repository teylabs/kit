<?php

namespace Tey\Kit\Features\Access\Policies;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Entitlements;
use Tey\Kit\Features\Access\Permissions;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Core\Users;

/** Who may do what in a workspace: its active members, by the permissions their roles there grant. */
class WorkspacePolicy
{
    public function view(Authenticatable $user, Workspace $workspace): bool
    {
        return Users::is($user) && (bool) RequestMemo::membership($user, $workspace)?->isActive();
    }

    public function update(Authenticatable $user, Workspace $workspace): bool
    {
        return $this->can($user, 'workspace.settings.update', $workspace);
    }

    /** People and its modals and writes: absent (a 404) while the capability behind `members.manage` is off or out of the plan, then the member's permission. */
    public function manageMembers(Authenticatable $user, Workspace $workspace): Response|bool
    {
        $capability = app(Registry::class)->capabilityFor('members.manage');
        if ($capability !== null && (! app(Capabilities::class)->active($capability, $workspace, Users::is($user) ? $user : null) || ! app(Entitlements::class)->included($capability, $workspace))) {
            return Response::denyAsNotFound();
        }

        return $this->can($user, 'workspace.members.manage', $workspace);
    }

    private function can(Authenticatable $user, string $permission, Workspace $workspace): bool
    {
        return Users::is($user) && app(Permissions::class)->allows($user, $permission, $workspace);
    }
}

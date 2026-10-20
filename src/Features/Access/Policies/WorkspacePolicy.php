<?php

namespace Tey\Kit\Features\Access\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Access\Permissions;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/** Who may do what in a workspace: its active members, by the permissions their roles there grant. */
class WorkspacePolicy
{
    public function view(Authenticatable $user, Workspace $workspace): bool
    {
        return Users::is($user) && (bool) app(Concierge::class)->membership($user, $workspace)?->isActive();
    }

    public function update(Authenticatable $user, Workspace $workspace): bool
    {
        return $this->can($user, 'workspace.settings.update', $workspace);
    }

    public function manageMembers(Authenticatable $user, Workspace $workspace): bool
    {
        return $this->can($user, 'workspace.members.manage', $workspace);
    }

    private function can(Authenticatable $user, string $permission, Workspace $workspace): bool
    {
        return Users::is($user) && app(Permissions::class)->allows($user, $permission, $workspace);
    }
}

<?php

namespace Tey\Kit\Features\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Support\Roles;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/** Permission checks in one workspace's team context. Roles are Concierge's; Pennant capabilities stay a separate gate. */
final class Permissions
{
    /** @var ?array<string, bool> one gate result per user, permission and workspace for the request that began with `remember()`; null (the default) decides afresh every time */
    private ?array $memo = null;

    /** Begins a request: gate results are memoized from here (the shell, the page's layout and the page share them), starting empty. */
    public function remember(): void
    {
        $this->memo = [];
    }

    /** Whether the member's roles in the workspace grant the permission. A permission no feature declares is never granted. */
    public function granted(Member&Model $user, string $permission, Workspace $workspace): bool
    {
        if ($this->memo === null) {
            return $this->decide($user, $permission, $workspace);
        }

        return $this->memo['granted:'.$user->getKey().':'.$permission.':'.$workspace->getKey()] ??= $this->decide($user, $permission, $workspace);
    }

    private function decide(Member&Model $user, string $permission, Workspace $workspace): bool
    {
        if (! isset(app(Registry::class)->permissionCatalog()[$permission])) {
            return false;
        }

        return app(Concierge::class)->context()->run($workspace, function () use ($user, $permission): bool {
            try {
                return $user->hasPermissionTo($permission, Roles::guard());
            } catch (PermissionDoesNotExist) {
                return false;
            }
        }, [$user]);
    }

    /** Active member, role grant, and the permission's capability active in the workspace: all three. */
    public function allows(Member&Model $user, string $permission, Workspace $workspace): bool
    {
        if ($this->memo === null) {
            return $this->decideAllows($user, $permission, $workspace);
        }

        return $this->memo['allows:'.$user->getKey().':'.$permission.':'.$workspace->getKey()] ??= $this->decideAllows($user, $permission, $workspace);
    }

    private function decideAllows(Member&Model $user, string $permission, Workspace $workspace): bool
    {
        $declared = app(Registry::class)->permissionCatalog()[$permission] ?? null;
        if ($declared === null || ! app(Concierge::class)->membership($user, $workspace)?->isActive()) {
            return false;
        }
        if ($declared['capability'] !== null && ! Feature::for($workspace)->active($declared['capability'])) {
            return false;
        }

        return $this->granted($user, $permission, $workspace);
    }

    /**
     * The `can` flags an Inertia page carries: permission name => allowed, for the workspace. Always from the policy
     * checks above, never from role names.
     *
     * @return array<string, bool>
     */
    public function flags(Member&Model $user, Workspace $workspace): array
    {
        return array_map(fn (string $permission): bool => $this->allows($user, $permission, $workspace), array_combine($names = array_keys(app(Registry::class)->permissionCatalog()), $names));
    }

    /** The `can` map for the request's current workspace (the route's id, as the shell reads it); empty for a guest or outside a workspace. */
    public function forRequest(Request $request): array
    {
        $user = $request->user();
        $id = $request->route('workspace');
        if (! Users::is($user) || ! is_numeric($id) || ($workspace = Workspace::query()->find((int) $id)) === null) {
            return [];
        }

        return $this->flags($user, $workspace);
    }
}

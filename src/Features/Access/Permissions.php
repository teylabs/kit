<?php

namespace Tey\Kit\Features\Access;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Support\Roles;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
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

        if ($this->memo === null) {
            return app(Concierge::class)->context()->run($workspace, function () use ($user, $permission): bool {
                try {
                    return $user->hasPermissionTo($permission, Roles::guard());
                } catch (PermissionDoesNotExist) {
                    return false;
                }
            }, [$user]);
        }
        // In a request, the member's whole permission set in the workspace is read once (roles and direct grants), then each check is a lookup.
        $key = 'set:'.$user->getKey().':'.$workspace->getKey();
        if (! isset($this->memo[$key])) {
            $this->memo[$key] = app(Concierge::class)->context()->run($workspace, fn (): array => $user->getAllPermissions()->where('guard_name', Roles::guard())->pluck('name')->flip()->map(fn (): bool => true)->all(), [$user]);
        }

        return isset($this->memo[$key][$permission]);
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
        if ($declared === null || ! RequestMemo::membership($user, $workspace)?->isActive()) {
            return false;
        }
        if ($declared['capability'] !== null && ! app(Capabilities::class)->active($declared['capability'], $workspace, $user)) {
            return false;
        }

        return $this->granted($user, $permission, $workspace);
    }
}

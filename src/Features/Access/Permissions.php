<?php

namespace Tey\Kit\Features\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Contracts\Workspace;
use Tey\Concierge\Support\Roles;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Workspaces\Rls;

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

    /**
     * Reads the member's permission sets in several workspaces at once, for a request that checks in each of them (the shell's switcher): their roles there (a
     * global role or that workspace's own, as spatie's team-scoped relation reads them), those roles' permissions and their direct grants, three queries for any
     * number of workspaces. Each is the set the request would read in that workspace's team context; a workspace already read keeps its set. Nothing is read
     * outside a request, without spatie teams, or while RLS hides other workspaces' role or grant rows (each set is then read in its own workspace, as before).
     *
     * @param  list<Workspace>  $workspaces
     */
    public function prime(Member&Model $user, array $workspaces): void
    {
        if ($this->memo === null) {
            return;
        }
        $registrar = app(PermissionRegistrar::class);
        $tables = (array) config('permission.table_names');
        // Every joined table with a workspace column must be readable across workspaces: a hidden role or grant row would seed a set too small.
        if (! $registrar->teams || ! Rls::shared($tables['roles']) || ! Rls::shared($tables['model_has_roles']) || ! Rls::shared($tables['model_has_permissions'])) {
            return;
        }
        $ids = array_values(array_filter(array_map(fn (Workspace $workspace): int => (int) $workspace->getKey(), $workspaces), fn (int $id): bool => ! isset($this->memo['set:'.$user->getKey().':'.$id])));
        if ($ids === []) {
            return;
        }
        [$team, $morph, $roles, $permissions] = [$registrar->teamsKey, (string) config('permission.column_names.model_morph_key'), $tables['roles'], $tables['permissions']];
        [$roleKey, $permissionKey] = [$roles.'.'.app($registrar->getRoleClass())->getKeyName(), $permissions.'.'.app($registrar->getPermissionClass())->getKeyName()];
        // The member's rows in a pivot, in these workspaces only.
        $mine = fn (string $pivot) => DB::table($pivot)->where($pivot.'.model_type', $user->getMorphClass())->where($pivot.'.'.$morph, $user->getKey())->whereIn($pivot.'.'.$team, $ids);
        $held = $mine($tables['model_has_roles'])->join($roles, $roleKey, '=', $tables['model_has_roles'].'.'.$registrar->pivotRole)
            ->where(fn ($query) => $query->whereNull($roles.'.'.$team)->orWhereColumn($roles.'.'.$team, $tables['model_has_roles'].'.'.$team))
            ->get([$tables['model_has_roles'].'.'.$team.' as workspace_id', $roleKey.' as role_id']);
        $viaRoles = $held->isEmpty() ? collect() : DB::table($permissions)->join($tables['role_has_permissions'], $permissionKey, '=', $tables['role_has_permissions'].'.'.$registrar->pivotPermission)
            ->whereIn($tables['role_has_permissions'].'.'.$registrar->pivotRole, $held->pluck('role_id')->unique()->values()->all())->where($permissions.'.guard_name', Roles::guard())
            ->get([$tables['role_has_permissions'].'.'.$registrar->pivotRole.' as role_id', $permissions.'.name'])->groupBy('role_id');
        $direct = $mine($tables['model_has_permissions'])->join($permissions, $permissionKey, '=', $tables['model_has_permissions'].'.'.$registrar->pivotPermission)
            ->where($permissions.'.guard_name', Roles::guard())->get([$tables['model_has_permissions'].'.'.$team.' as workspace_id', $permissions.'.name']);
        $sets = array_fill_keys($ids, []);
        foreach ($held as $row) {
            foreach ($viaRoles[$row->role_id] ?? [] as $grant) {
                $sets[(int) $row->workspace_id][$grant->name] = true;
            }
        }
        foreach ($direct as $row) {
            $sets[(int) $row->workspace_id][$row->name] = true;
        }
        foreach ($sets as $id => $set) {
            $this->memo['set:'.$user->getKey().':'.$id] = $set;
        }
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

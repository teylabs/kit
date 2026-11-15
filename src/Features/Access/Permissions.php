<?php

namespace Tey\Kit\Features\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Contracts\Workspace;
use Tey\Concierge\Support\Roles;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;

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

    /** Empties the request's answers and permission sets when a role or grant changes (AccessProvider): the next check reads afresh. */
    public function forget(): void
    {
        $this->memo = $this->memo === null ? null : [];
        if (app()->resolved(RequestMemo::class)) {
            RequestMemo::instance()->flush();
        }
    }

    /**
     * Whether a statement, run on a connection with this table prefix, writes a role, a permission or one of their pivots, by any path (spatie, Concierge, the kit,
     * a raw query). A write is any insert (also `insert or ignore`, `insert ignore`, an upsert), replace, update, delete (also with joins or an alias), truncate or
     * merge, on every grammar, also behind a CTE. It writes one of the tables when it names it anywhere, as the grammar wrote it: the configured name's table part
     * with the connection's prefix (`kit_model_has_roles`), quoted or not, schema-qualified or not (`"auth"."kit_roles"`, `main.roles`). It errs toward a match:
     * a write that only names one elsewhere (a subquery, a column of that name) empties the sets too, which costs a read; a write missed would answer from a stale set.
     */
    public static function writes(string $sql, string $prefix = ''): bool
    {
        if (preg_match('/^\s*(?:insert|replace|update|delete|truncate|merge)\b/i', $sql) !== 1 && preg_match('/^\s*with\b.*\b(?:insert|update|delete|merge)\b/is', $sql) !== 1) {
            return false;
        }
        $names = [];
        foreach ((array) config('permission.table_names') as $table) {
            $table = Str::afterLast((string) $table, '.');
            array_push($names, preg_quote($prefix.$table, '/'), preg_quote($table, '/'));
        }

        return $names !== [] && preg_match('/(?<![\w$])(?:'.implode('|', array_unique($names)).')(?![\w$])/i', $sql) === 1;
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
        // In a request, the member's whole permission set in the workspace is read once (roles and direct grants; RequestMemo, on once()), then each check is a
        // lookup. A role or grant written since empties it: RequestMemo on the write statement, the answers here through forget().
        $set = RequestMemo::instance()->get('permissions:'.$user->getKey().':'.$workspace->getKey(), fn (): array => app(Concierge::class)->context()->run($workspace, fn (): array => $user->getAllPermissions()->where('guard_name', Roles::guard())->pluck('name')->flip()->map(fn (): bool => true)->all(), [$user]));

        return isset($set[$permission]);
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

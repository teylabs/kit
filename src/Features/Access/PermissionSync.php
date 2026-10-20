<?php

namespace Tey\Kit\Features\Access;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tey\Concierge\Support\Roles;
use Tey\Kit\Features\Core\Registry;

/**
 * Makes the database hold the declared permissions: creates the missing ones as global rows (like Concierge's roles)
 * and grants each its default roles. A default is granted once, when it is new: `kit_permission_defaults` records the
 * ones applied, so a default someone removed is not granted again. A default role the app does not configure is reported, not granted. Re-running changes nothing. It never revokes:
 * grants beyond the defaults, and permissions no feature declares, are kept and reported.
 */
final class PermissionSync
{
    /** @return array{created: list<string>, granted: list<string>, missing_roles: list<string>, unconfigured_roles: list<string>, custom: list<string>, undeclared: list<string>} */
    public function run(): array
    {
        $registrar = app(PermissionRegistrar::class);
        $guard = Roles::guard();
        $catalog = app(Registry::class)->permissionCatalog();
        $report = ['created' => [], 'granted' => [], 'missing_roles' => [], 'unconfigured_roles' => [], 'custom' => [], 'undeclared' => []];
        $roles = [];

        foreach ($catalog as $name => $declared) {
            $permission = $registrar->getPermissionClass()::query()->where('name', $name)->where('guard_name', $guard)->first();
            if ($permission === null) {
                $permission = $registrar->getPermissionClass()::create(['name' => $name, 'guard_name' => $guard]);
                $report['created'][] = $name;
            }
            foreach ($declared['roles'] as $roleName) {
                $role = $roles[$roleName] ??= Roles::find($roleName);
                if ($role === null && ! in_array($roleName, Roles::configured(), true)) {
                    // A role the app does not use (a starter with only owner and viewer has no admin): nothing to grant, nothing to fail.
                    $report['unconfigured_roles'][$roleName] = $roleName;

                    continue;
                }
                if ($role === null) {
                    $report['missing_roles'][$roleName] = $roleName;
                    // A role that is gone took its grants with it: when it comes back, its defaults are new again.
                    DB::table('kit_permission_defaults')->where('role', $roleName)->delete();

                    continue;
                }
                $applied = DB::table('kit_permission_defaults');
                if ($applied->clone()->where('permission', $name)->where('role', $roleName)->exists()) {
                    continue;
                }
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                    $report['granted'][] = $roleName.' '.$name;
                }
                $applied->insert(['permission' => $name, 'role' => $roleName, 'applied_at' => now()]);
            }
        }
        $report['missing_roles'] = array_values($report['missing_roles']);
        $report['unconfigured_roles'] = array_values($report['unconfigured_roles']);

        foreach ($registrar->getPermissionClass()::query()->where('guard_name', $guard)->with('roles')->get() as $permission) {
            if (! isset($catalog[$permission->name])) {
                $report['undeclared'][] = $permission->name;

                continue;
            }
            foreach ($permission->roles as $role) {
                if (! in_array($role->name, $catalog[$permission->name]['roles'], true)) {
                    $report['custom'][] = $role->name.' '.$permission->name;
                }
            }
        }
        $registrar->forgetCachedPermissions();

        return $report;
    }
}

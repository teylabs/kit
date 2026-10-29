<?php

namespace Tey\Kit\Features\Workspaces;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Spatie\Multitenancy\Contracts\IsTenant;
use Tey\Concierge\Contracts\Workspace;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Workspaces\Concerns\IsKitWorkspace;

/**
 * The bound workspace model. Kit code reaches the workspace through here, never through `Models\Workspace`, so a host that keeps its
 * own workspaces table binds its own model (`concierge.models.workspace` and `multitenancy.tenant_model`) and the kit follows it.
 */
final class Workspaces
{
    /** @return class-string<Model&IsTenant&Workspace> */
    public static function model(): string
    {
        return ConciergeModels::workspace();
    }

    /** @return Builder<Model&IsTenant&Workspace> */
    public static function query(): Builder
    {
        return self::model()::query();
    }

    public static function current(): (Model&IsTenant&Workspace)|null
    {
        return self::model()::current();
    }

    public static function required(): Model&IsTenant&Workspace
    {
        return self::current() ?? throw new RuntimeException('workspace_required');
    }

    /**
     * Runs `$fn` with the workspace as the current tenant, through spatie's `execute()`: the switch tasks run (Concierge's context, the
     * permission team, a Pennant flush), and the tenant that was current before, or none, is restored afterwards, also when `$fn` throws.
     * An id is looked up on the bound model and an unknown one throws `ModelNotFoundException` before anything switches. Pass a block
     * closure, not an arrow function: an arrow function returns what `dispatch()` returns, and that job is queued only once it is
     * discarded, after the tenant is restored.
     *
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    public static function run(Workspace|int $workspace, Closure $fn): mixed
    {
        $tenant = is_int($workspace) ? self::query()->findOrFail($workspace) : $workspace;
        if (! $tenant instanceof IsTenant) {
            throw new RuntimeException('workspace_not_tenant');
        }

        return $tenant->execute(function () use ($fn): mixed {
            return $fn();
        });
    }

    /**
     * Why the bound model cannot serve as the kit's workspace, or null when it can: `concierge.models.workspace` and
     * `multitenancy.tenant_model` name one Eloquent model, which implements Concierge's Workspace contract and IsTenant and uses
     * IsKitWorkspace. Defaults to the values config has now; a plan passes the values the app will run with.
     */
    public static function refusal(mixed $concierge = null, mixed $tenant = null, string $contract = Workspace::class, string $trait = IsKitWorkspace::class): ?string
    {
        $concierge = func_num_args() > 0 ? $concierge : config('concierge.models.workspace');
        $tenant = func_num_args() > 1 ? $tenant : config('multitenancy.tenant_model');
        if (! is_string($concierge) || ! is_string($tenant) || ltrim($concierge, '\\') !== ltrim($tenant, '\\')) {
            return 'concierge.models.workspace ('.(is_string($concierge) ? $concierge : 'unset').') and multitenancy.tenant_model ('.(is_string($tenant) ? $tenant : 'unset').') must name the same workspace model. Publish config/concierge.php and config/multitenancy.php and set both to your workspace model class.';
        }
        if (! class_exists($concierge)) {
            return 'The workspace model '.$concierge.' (concierge.models.workspace) does not exist.';
        }
        $missing = array_values(array_filter([
            is_subclass_of($concierge, Model::class) ? null : 'extend '.Model::class,
            is_subclass_of($concierge, $contract) ? null : 'implement '.$contract,
            is_subclass_of($concierge, IsTenant::class) ? null : 'implement '.IsTenant::class,
            in_array($trait, class_uses_recursive($concierge), true) ? null : 'use '.$trait,
        ]));

        return $missing === [] ? null : 'The workspace model '.$concierge.' must '.implode(', ', $missing).'.';
    }
}

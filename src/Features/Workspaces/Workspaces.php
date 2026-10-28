<?php

namespace Tey\Kit\Features\Workspaces;

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

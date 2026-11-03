<?php

namespace Tey\Kit\Features\Workspaces;

use Closure;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\PendingChain;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Facades\Log;
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
     * discarded, after the tenant is restored. It fails closed if `$fn` returns a pending dispatch anyway (a `PendingDispatch` or
     * `PendingClosureDispatch`, a `PendingChain` or a `PendingBatch`), from any closure: the job is dispatched inside the workspace
     * (the reference dropped, or `dispatch()` called for a chain or batch), `run()` returns null, and one warning per call site names
     * the job. It never throws there, as the job would still be queued while the exception unwinds.
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
            $result = $fn();
            if (! $result instanceof PendingDispatch && ! $result instanceof PendingChain && ! $result instanceof PendingBatch) {
                return $result;
            }
            $job = match (true) {
                $result instanceof PendingDispatch => $result->getJob(),
                $result instanceof PendingChain => $result->job,
                default => $result->jobs->first(),
            };
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2] ?? [];
            $site = ($caller['file'] ?? '?').':'.($caller['line'] ?? '?');
            if ($result instanceof PendingDispatch) {
                // Queued by its destructor, here, while the workspace is still current.
                unset($result);
            } else {
                $result->dispatch();
            }
            if (! isset(self::$warnedSites[$site])) {
                self::$warnedSites[$site] = true;
                Log::warning('Workspaces::run() callback returned a pending dispatch; it was dispatched inside the workspace and run() returns null', ['job' => is_object($job) ? $job::class : get_debug_type($job), 'site' => $site]);
            }

            return null;
        });
    }

    /** @var array<string, true> the call sites that have already logged a returned pending dispatch */
    private static array $warnedSites = [];

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

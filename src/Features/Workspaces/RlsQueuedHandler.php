<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\CallQueuedHandler;
use RuntimeException;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;

/**
 * The queue's job runner with RLS on. A tenant-aware job (spatie's `TenantAware`, `multitenancy.tenant_aware_jobs`, or every job not declared otherwise
 * when `queues_are_tenant_aware_by_default` is on) runs in one transaction carrying its workspace id (Rls::within), the workspace spatie restored from the
 * payload before the job started; with no current workspace it fails closed (`rls_workspace_required`) before its middleware or handle() runs. Any other
 * job (`NotTenantAware`, `multitenancy.not_tenant_aware_jobs`, or undeclared with the default off, the kit's default since v0.38.0) runs with no
 * workspace id set, so protected tables show it nothing and refuse its inserts. The rule is spatie's own, so the two never disagree.
 */
class RlsQueuedHandler extends CallQueuedHandler
{
    protected function dispatchThroughMiddleware(Job $job, $command)
    {
        if (! Rls::enabled() || ! self::tenantAware($command)) {
            return parent::dispatchThroughMiddleware($job, $command);
        }
        $workspace = Workspaces::current() ?? throw new RuntimeException('rls_workspace_required: '.(is_object($command) ? $command::class : get_debug_type($command)).' ran with no current workspace');

        return Rls::within((int) $workspace->getKey(), fn (): mixed => parent::dispatchThroughMiddleware($job, $command));
    }

    /** Spatie's order: the interfaces, then the lists, then the default. A queued mailable, notification or listener is declared on the class it wraps. */
    public static function tenantAware(mixed $command): bool
    {
        $inner = match (true) {
            isset($command->mailable) => $command->mailable,
            isset($command->notification) => $command->notification,
            isset($command->class) && is_string($command->class) => $command->class,
            default => $command,
        };
        $class = is_object($inner) ? $inner::class : (string) $inner;
        if (is_a($class, (string) config('multitenancy.tenant_aware_interface', TenantAware::class), true)) {
            return true;
        }
        if (is_a($class, (string) config('multitenancy.not_tenant_aware_interface', NotTenantAware::class), true)) {
            return false;
        }
        $listed = fn (string $key): bool => in_array($class, (array) config($key, []), true) || (is_object($command) && in_array($command::class, (array) config($key, []), true));
        if ($listed('multitenancy.tenant_aware_jobs')) {
            return true;
        }
        if ($listed('multitenancy.not_tenant_aware_jobs')) {
            return false;
        }

        return config('multitenancy.queues_are_tenant_aware_by_default') === true;
    }
}

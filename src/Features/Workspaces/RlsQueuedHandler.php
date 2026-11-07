<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\CallQueuedHandler;
use RuntimeException;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * The queue's job runner with RLS on: each job runs in one transaction carrying its workspace id (Rls::within), the workspace spatie restored from
 * the payload before the job started. A job with no current workspace fails closed (`rls_workspace_required`) before its middleware or handle()
 * runs, unless it is declared not tenant-aware (spatie's NotTenantAware, or `multitenancy.not_tenant_aware_jobs`): those run with no workspace
 * id set, so protected tables show them nothing and refuse their inserts.
 */
class RlsQueuedHandler extends CallQueuedHandler
{
    protected function dispatchThroughMiddleware(Job $job, $command)
    {
        if (! Rls::enabled() || self::notTenantAware($command)) {
            return parent::dispatchThroughMiddleware($job, $command);
        }
        $workspace = Workspaces::current() ?? throw new RuntimeException('rls_workspace_required: '.(is_object($command) ? $command::class : get_debug_type($command)).' ran with no current workspace');

        return Rls::within((int) $workspace->getKey(), fn (): mixed => parent::dispatchThroughMiddleware($job, $command));
    }

    private static function notTenantAware(mixed $command): bool
    {
        // A queued mailable, notification or listener is declared on the class it wraps.
        $inner = match (true) {
            isset($command->mailable) => $command->mailable,
            isset($command->notification) => $command->notification,
            isset($command->class) && is_string($command->class) => $command->class,
            default => $command,
        };
        $class = is_object($inner) ? $inner::class : (string) $inner;

        return is_a($class, NotTenantAware::class, true) || in_array($class, (array) config('multitenancy.not_tenant_aware_jobs', []), true)
            || (is_object($command) && in_array($command::class, (array) config('multitenancy.not_tenant_aware_jobs', []), true));
    }
}

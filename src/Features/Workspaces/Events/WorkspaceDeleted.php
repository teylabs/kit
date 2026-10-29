<?php

namespace Tey\Kit\Features\Workspaces\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A workspace was deleted, with the kit's own rows. Dispatched only after the deletion's transaction commits (ShouldDispatchAfterCommit), so a listener never
 * sees an uncommitted deletion and nothing fires on a rollback. The workspace no longer exists: a queued purge listener cannot make it current (a TenantAware
 * listener would fail to find it), so it is a plain queued listener (or NotTenantAware) that works from these ids. See docs/lifecycle-events.md.
 */
final class WorkspaceDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param  list<int>  $memberUserIds  every user who had a membership row in the workspace, in any status */
    public function __construct(public readonly int $workspaceId, public readonly string $slug, public readonly array $memberUserIds, public readonly int $actorId) {}
}

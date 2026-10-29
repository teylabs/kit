<?php

namespace Tey\Kit\Features\Workspaces\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A workspace is about to be deleted: dispatched inside the deletion's transaction, before any row goes, with the workspace still current. Informational only:
 * there is no veto. A listener runs synchronously and may read the workspace's rows; it must not queue work (the transaction may still roll back). A host purges
 * its own data on WorkspaceDeleted, which is dispatched after the commit.
 */
final class WorkspaceDeleting
{
    use Dispatchable;

    /** @param  list<int>  $memberUserIds  every user with a membership row in the workspace, in any status */
    public function __construct(public readonly int $workspaceId, public readonly string $slug, public readonly array $memberUserIds, public readonly int $actorId) {}
}

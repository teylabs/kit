<?php

namespace Tey\Kit\Features\Workspaces\Operations;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Workspaces\Events\WorkspaceDeleted;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * The end of a deferred workspace deletion: the host's job calls `app(FinishWorkspaceDeletion::class)($workspaceId)` once its own data is gone. It deletes the
 * kit's and Concierge's rows and then the workspace row, in one short transaction under the row's lock, and dispatches WorkspaceDeleted after the commit.
 *
 * Idempotent and retry-safe: a workspace already gone is a no-op (false), so a retried job finishes cleanly. Fails closed: a workspace that is not being deleted
 * (no `deleting_at`) is refused with `workspace_not_deleting` and nothing is deleted. A failure rolls back, leaving the workspace closed for the next try.
 */
final class FinishWorkspaceDeletion
{
    /** @return bool true when this call deleted the workspace, false when it was already gone */
    public function __invoke(int $workspaceId): bool
    {
        return DB::transaction(function () use ($workspaceId): bool {
            $workspace = Workspaces::query()->lockForUpdate()->find($workspaceId);
            if ($workspace === null) {
                return false;
            }
            if (! $workspace->isDeleting()) {
                throw new RuntimeException('workspace_not_deleting');
            }
            $memberUserIds = array_values(array_map('intval', ConciergeModels::membership()::query()->where('workspace_id', $workspaceId)->orderBy('user_id')->pluck('user_id')->all()));
            app(DeleteWorkspace::class)->deleteRows($workspaceId);
            $workspace->delete();
            // ShouldDispatchAfterCommit: held until the transaction commits, dropped on a rollback.
            WorkspaceDeleted::dispatch($workspaceId, (string) ($workspace->slug ?? ''), $memberUserIds, (int) $workspace->getAttribute('deleting_by'));

            return true;
        });
    }
}

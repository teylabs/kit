<?php

namespace Tey\Kit\Features\Workspaces\Operations;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\Concerns\BelongsToWorkspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Moves one workspace-owned row (BelongsToWorkspace) from the current workspace to another, keeping its id, so rows that reference it stay attached: an app
 * re-bound to a new workspace, instead of a delete and re-create. Fails closed:
 * - the row must exist and belong to the current workspace (the source), as stored: another workspace's row is `workspace_mismatch`;
 * - the target must exist, and differ from the source;
 * - the actor must hold the move ability in BOTH workspaces (`kit.workspaces.move_ability`, by default `members.manage`, the ability that manages a
 *   workspace's people and settings), checked through Access for the Principal in each workspace's own context: membership, a verified active account,
 *   admission, the permission, the capability and the plan. Without it in either one it is a 403 carrying Access's refusal code (`permission_denied`, …);
 * - the column is rewritten by one query matching the row only while it is still in the source (`where column = source`): that is the race protection, as
 *   a concurrent move or change updates 0 rows and throws `workspace_mismatch`, rolling the transaction back. The query fires no model events, so
 *   BelongsToWorkspace's updating guard needs no bypass, public or otherwise, and still refuses every ordinary update.
 * It writes the audit event `workspace.row.moved` (from, to, model, id) in the source workspace, in the same transaction.
 */
final class MoveToWorkspace
{
    public static function run(Model $model, Model|int $target, Principal $actor): Model
    {
        if (! in_array(BelongsToWorkspace::class, class_uses_recursive($model), true)) {
            throw new RuntimeException('not_workspace_owned');
        }
        $column = $model::workspaceColumn();
        $source = (int) Workspaces::required()->getKey();
        $to = $target instanceof Model ? (int) $target->getKey() : $target;
        if (! $model->exists || (int) $model->getOriginal($column) !== $source || (int) $model->getAttribute($column) !== $source) {
            throw new RuntimeException('workspace_mismatch');
        }
        if ($to === $source) {
            throw new RuntimeException('workspace_same');
        }
        $workspaces = Workspaces::model()::query()->whereKey([$source, $to])->get()->keyBy(fn (Model $workspace): int => (int) $workspace->getKey());
        if (! $workspaces->has($to)) {
            throw new RuntimeException('workspace_not_found');
        }
        $ability = (string) config('kit.workspaces.move_ability', 'members.manage');
        foreach ([$source, $to] as $id) {
            try {
                // The target's check runs in the target's own tenant context (restored after), as any operation there would.
                $id === $source ? app(Access::class)->authorize($actor, $ability) : app(TenantBoundary::class)->run($id, fn () => app(Access::class)->authorize($actor, $ability));
            } catch (RuntimeException $refused) {
                Response::deny('You need '.$ability.' in both workspaces to move this.', $refused->getMessage())->withStatus(403)->authorize();
            }
        }

        DB::transaction(function () use ($model, $column, $source, $to, $actor): void {
            $values = [$column => $to] + ($model->usesTimestamps() && $model->getUpdatedAtColumn() !== null ? [$model->getUpdatedAtColumn() => $model->freshTimestampString()] : []);
            $moved = $model->newQueryWithoutScopes()->whereKey($model->getKey())->where($column, $source)->update($values);
            if ($moved !== 1) {
                throw new RuntimeException('workspace_mismatch');
            }
            app(Audit::class)->write($actor->actorId, $source, 'workspace.row.moved', ['from' => $source, 'to' => $to, 'model' => $model->getMorphClass(), 'id' => $model->getKey()]);
        });
        $model->forceFill([$column => $to])->syncOriginalAttribute($column);

        return $model;
    }
}

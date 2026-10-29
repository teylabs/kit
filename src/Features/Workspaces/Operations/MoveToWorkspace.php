<?php

namespace Tey\Kit\Features\Workspaces\Operations;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Concerns\BelongsToWorkspace;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Moves one workspace-owned row (BelongsToWorkspace) from the current workspace to another, keeping its id, so rows that reference it stay attached: an app
 * re-bound to a new workspace, instead of a delete and re-create. Fails closed:
 * - the row must exist and belong to the current workspace (the source), as stored: another workspace's row is `workspace_mismatch`;
 * - the target must exist, and differ from the source;
 * - the actor must be able to act in both workspaces (`view` on each, an active membership), else it is refused (`not_member`);
 * - the column is rewritten by one query matching the row only while it is still in the source (a concurrent move or change is `workspace_mismatch`). That
 *   query fires no model events, so BelongsToWorkspace's updating guard needs no bypass, public or otherwise, and still refuses every ordinary update.
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
        $user = Users::model()::query()->find($actor->actorId);
        foreach ([$source, $to] as $id) {
            if ($user === null || ! Gate::forUser($user)->allows('view', $workspaces[$id])) {
                Response::deny('You must be a member of both workspaces to move this.', 'not_member')->authorize();
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

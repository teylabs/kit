<?php

namespace Tey\Kit\Features\Access\Operations;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Core\RequestMemo;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\Concerns\BelongsToWorkspace;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Moves one workspace-owned row (BelongsToWorkspace) from the current workspace to another, keeping its id, so rows that reference it stay attached: an app
 * re-bound to a new workspace, instead of a delete and re-create. Fails closed:
 * - the row must exist and belong to the current workspace (the source), as stored: another workspace's row is `workspace_mismatch`;
 * - the target must exist, and differ from the source;
 * - the model may name its own move ability with `workspaceMoveAbility(): string`, a Gate ability or policy method checked in each workspace with the row:
 *   `Gate::allows($ability, [$row, $workspace])`, so an app's policy can say "the row's owner may move it". The actor must still be an active member of BOTH
 *   workspaces first, and an ability no gate or policy defines is refused (`ability_unknown`);
 * - without it, the actor must hold the move ability in BOTH workspaces (`kit.workspaces.move_ability`, by default `members.manage`, the ability that manages a
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
        // The model may name its own move ability (row-aware: the row is passed to the check); else the kit's Access ability in both workspaces.
        $own = method_exists($model, 'workspaceMoveAbility') ? (string) $model->workspaceMoveAbility() : null;
        $ability = $own ?? (string) config('kit.workspaces.move_ability', 'members.manage');
        $user = RequestMemo::user($actor->actorId);
        foreach ([$source, $to] as $id) {
            // Each check runs in that workspace's own tenant context (restored after), as any operation there would.
            $check = function () use ($own, $ability, $actor, $user, $model, $workspaces, $id): void {
                if ($own === null) {
                    app(Access::class)->authorize($actor, $ability);

                    return;
                }
                // Whatever the model's ability says, the actor must be an active member of the workspace first.
                $membership = $user instanceof Member ? RequestMemo::membership($user, $workspaces[$id]) : null;
                if (! $membership?->isActive()) {
                    throw new RuntimeException('workspace_not_found');
                }
                // Fail closed: an ability no gate or policy defines is refused, never treated as allowed or as the default.
                $policy = Gate::getPolicyFor($model);
                if (! Gate::has($ability) && ($policy === null || ! method_exists($policy, Str::camel($ability)))) {
                    throw new RuntimeException('ability_unknown');
                }
                if (! Gate::forUser($user)->allows($ability, [$model, $workspaces[$id]])) {
                    throw new RuntimeException('permission_denied');
                }
            };
            try {
                $id === $source ? $check() : Workspaces::run($id, $check);
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

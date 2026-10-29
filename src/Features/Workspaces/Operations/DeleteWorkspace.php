<?php

namespace Tey\Kit\Features\Workspaces\Operations;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\Locks;
use Tey\Kit\Features\Workspaces\Events\WorkspaceDeleted;
use Tey\Kit\Features\Workspaces\Events\WorkspaceDeleting;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Deletes the current workspace and the rows the kit and Concierge keep for it, in one transaction. Concierge has no deletion action, so this is the kit's.
 * Only the workspace's owner may, with the workspace's name typed to confirm (checked here, on the server). WorkspaceDeleting is dispatched inside the
 * transaction before anything is deleted; WorkspaceDeleted after the commit, with the ids a host needs to purge its own data.
 *
 * `audit_events` rows are never deleted: the audit trail outlives the workspace (its foreign key nulls `workspace_id` where the schema allows it).
 * A host's own tables that reference the workspace are the host's to clear, in a WorkspaceDeleting listener (same transaction) or on WorkspaceDeleted.
 */
class DeleteWorkspace
{
    use AsAction;

    public function handle(Model $actor, string $confirmation): void
    {
        $workspace = Workspaces::required();
        // Not an active member: the workspace is absent (404), as it is to anyone outside it.
        abort_unless(Gate::forUser($actor)->allows('view', $workspace), 404);
        $this->authorizeOwner($actor, (int) $workspace->getKey());
        if (trim($confirmation) !== (string) $workspace->name) {
            throw ValidationException::withMessages(['confirmation' => 'Type the workspace name exactly to confirm.']);
        }

        DB::transaction(function () use ($actor, $workspace): void {
            $id = (int) $workspace->getKey();
            Locks::workspace($id);
            // Checked again under the lock: ownership may have moved since the request was read.
            $this->authorizeOwner($actor, $id);
            $memberships = ConciergeModels::membership()::query()->where('workspace_id', $id);
            $memberUserIds = array_values(array_map('intval', $memberships->clone()->orderBy('user_id')->pluck('user_id')->all()));
            $slug = (string) ($workspace->slug ?? '');

            WorkspaceDeleting::dispatch($id, $slug, $memberUserIds, (int) $actor->getKey());

            $this->deleteRows($id);
            $workspace->delete();

            // ShouldDispatchAfterCommit: held until the outermost transaction commits, and dropped on a rollback.
            WorkspaceDeleted::dispatch($id, $slug, $memberUserIds, (int) $actor->getKey());
        });
    }

    private function authorizeOwner(Model $actor, int $workspaceId): void
    {
        $membership = $actor instanceof Member ? ConciergeModels::membership()::query()->where('workspace_id', $workspaceId)->where('user_id', $actor->getKey())->first() : null;
        if ($membership === null || $membership->status !== MembershipStatus::Active || ! app(Concierge::class)->isOwner($membership)) {
            Response::deny('Only the workspace owner can delete it.', 'not_owner')->authorize();
        }
    }

    /** The kit's and Concierge's rows for the workspace, children first. A table a host did not migrate is skipped. */
    private function deleteRows(int $id): void
    {
        $where = fn (string $table, string $column = 'workspace_id') => Schema::hasTable($table) && Schema::hasColumn($table, $column) ? DB::table($table)->where($column, $id)->delete() : 0;
        if (Schema::hasTable('connections') && Schema::hasTable('kit_mcp_intents')) {
            DB::table('kit_mcp_intents')->whereIn('connection_id', DB::table('connections')->where('workspace_id', $id)->select('id'))->delete();
        }
        // `complimentary_grants` and `entitlements` are commercial records on RESTRICT foreign keys: they are deleted here, explicitly, before the workspace row.
        // `kit_entitlements` and `kit_capability_overrides` cascade, and are cleared here too for a connection that does not enforce foreign keys.
        foreach (['kit_note_intents', 'kit_notes', 'connections', 'settings_intents', 'complimentary_grants', 'entitlements', 'kit_entitlements', 'kit_capability_overrides', 'kit_user_state'] as $table) {
            $where($table);
        }
        $team = (string) config('permission.column_names.team_foreign_key', 'workspace_id');
        foreach (['model_has_roles', 'model_has_permissions', 'roles'] as $key) {
            $where((string) config('permission.table_names.'.$key, $key), $team);
        }
        $settings = (string) (config('settings.repositories.database.table') ?? 'settings');
        if (Schema::hasTable($settings)) {
            DB::table($settings)->where('group', 'workspace.'.$id)->delete();
        }
        foreach ([ConciergeModels::invitation(), ConciergeModels::tenantInvitation(), ConciergeModels::ownership(), ConciergeModels::membership()] as $model) {
            $where((new $model)->getTable());
        }
    }
}

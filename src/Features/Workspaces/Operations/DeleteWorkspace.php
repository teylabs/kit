<?php

namespace Tey\Kit\Features\Workspaces\Operations;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\Locks;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Kit;
use Tey\Kit\Features\Workspaces\Contracts\DefersWorkspacePurge;
use Tey\Kit\Features\Workspaces\Contracts\PurgesWorkspaceData;
use Tey\Kit\Features\Workspaces\Events\WorkspaceDeleted;
use Tey\Kit\Features\Workspaces\Events\WorkspaceDeleting;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Deletes the current workspace and the rows the kit and Concierge keep for it, in one transaction. Concierge has no deletion action, so this is the kit's.
 * Only the workspace's owner may, with the workspace's name typed to confirm (checked here, on the server). WorkspaceDeleting is dispatched inside the
 * transaction before anything is deleted; WorkspaceDeleted after the commit, with the ids a host needs to purge its own data.
 *
 * `audit_events` rows are never deleted: the audit trail outlives the workspace (its foreign key nulls `workspace_id` where the schema allows it).
 * A host's own tables that reference the workspace are the host's to clear, in its PurgesWorkspaceData handler (same transaction, first) or on WorkspaceDeleted.
 *
 * Off unless `kit.lifecycle.workspace_delete` is on AND a PurgesWorkspaceData handler is bound: otherwise this refuses before reading anything, nothing deleted.
 *
 * Deferred, when the bound handler implements DefersWorkspacePurge: nothing is deleted here. The workspace is closed at once (`deleting_at`, `deleting_by`) in a
 * short transaction under the lock, WorkspaceDeleting is dispatched and the audit written there; then, after the commit, the handler's begin() queues the host's
 * job, which ends by calling FinishWorkspaceDeletion. No file or row of the host's is touched inside the kit's transaction.
 */
class DeleteWorkspace
{
    use AsAction;

    /** @return 'deleted'|'deleting' deleted now, or closed for the host's deferred purge */
    public function handle(Model $actor, string $confirmation, ?string $password = null): string
    {
        $purger = self::purger();
        $workspace = Workspaces::required();
        // Not an active member: the workspace is absent (404), as it is to anyone outside it.
        abort_unless(Gate::forUser($actor)->allows('view', $workspace), 404);
        $this->authorizeOwner($actor, (int) $workspace->getKey());
        if (trim($confirmation) !== (string) $workspace->name) {
            throw ValidationException::withMessages(['confirmation' => 'Type the workspace name exactly to confirm.']);
        }
        // The current password too, unless kit.lifecycle.confirm_password is false exactly (fails closed: a missing password is refused).
        if (config('kit.lifecycle.confirm_password', true) !== false && ($password === null || ! Hash::check($password, (string) $actor->getAuthPassword()))) {
            throw ValidationException::withMessages(['password' => 'That password is not right.']);
        }

        if ($purger instanceof DefersWorkspacePurge) {
            return $this->close($actor, $workspace, $purger);
        }

        DB::transaction(function () use ($actor, $workspace, $purger): void {
            $id = (int) $workspace->getKey();
            Locks::workspace($id);
            // Checked again under the lock: ownership may have moved since the request was read.
            $this->authorizeOwner($actor, $id);
            $memberships = ConciergeModels::membership()::query()->where('workspace_id', $id);
            $memberUserIds = array_values(array_map('intval', $memberships->clone()->orderBy('user_id')->pluck('user_id')->all()));
            $slug = (string) ($workspace->slug ?? '');

            WorkspaceDeleting::dispatch($id, $slug, $memberUserIds, (int) $actor->getKey());
            // The host's own data first, while the kit's rows it may reference still exist; a throw rolls everything back.
            $purger->purge($workspace);

            $this->deleteRows($id);
            $workspace->delete();

            // ShouldDispatchAfterCommit: held until the outermost transaction commits, and dropped on a rollback.
            WorkspaceDeleted::dispatch($id, $slug, $memberUserIds, (int) $actor->getKey());
        });

        return 'deleted';
    }

    /** The deferred mode: closes the workspace now and hands the purge to the host's job. A workspace already closed is not closed again, nor begun twice. */
    private function close(Model $actor, Model $workspace, DefersWorkspacePurge $purger): string
    {
        if (! Schema::hasColumn($workspace->getTable(), 'deleting_at') || ! Schema::hasColumn($workspace->getTable(), 'deleting_by')) {
            throw new RuntimeException('Deferred workspace deletion needs the workspaces table\'s deleting_at and deleting_by columns: run `php artisan migrate`. Nothing was deleted.');
        }
        $closed = DB::transaction(function () use ($actor, $workspace): bool {
            $id = (int) $workspace->getKey();
            $locked = Locks::workspace($id);
            $this->authorizeOwner($actor, $id);
            if ($locked->isDeleting()) {
                return false;
            }
            $memberUserIds = array_values(array_map('intval', ConciergeModels::membership()::query()->where('workspace_id', $id)->orderBy('user_id')->pluck('user_id')->all()));
            Workspaces::query()->whereKey($id)->update(['deleting_at' => now(), 'deleting_by' => (int) $actor->getKey()]);
            WorkspaceDeleting::dispatch($id, (string) ($locked->slug ?? ''), $memberUserIds, (int) $actor->getKey());
            app(Audit::class)->write((int) $actor->getKey(), $id, 'workspace.deletion_started', ['members' => count($memberUserIds)]);

            return true;
        });
        if ($closed) {
            // After the commit and outside the kit's transaction: the host's job sees the workspace closed. A throw here leaves it closed, never reopened.
            $purger->begin($workspace->refresh());
        }

        return 'deleting';
    }

    /** The bound purge handler; refuses (a configuration error) when deletion is off or on without one. */
    public static function purger(): PurgesWorkspaceData
    {
        if (! filter_var(config('kit.lifecycle.workspace_delete', false), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('Workspace deletion is off: set kit.lifecycle.workspace_delete and bind '.PurgesWorkspaceData::class.' to turn it on.');
        }
        if (! app()->bound(PurgesWorkspaceData::class)) {
            throw new RuntimeException('kit.lifecycle.workspace_delete is on but no '.PurgesWorkspaceData::class.' is bound. Bind the purge handler for your own data (one that does nothing, explicitly, if you have none). Nothing was deleted.');
        }

        return app(PurgesWorkspaceData::class);
    }

    private function authorizeOwner(Model $actor, int $workspaceId): void
    {
        $membership = $actor instanceof Member ? ConciergeModels::membership()::query()->where('workspace_id', $workspaceId)->where('user_id', $actor->getKey())->first() : null;
        if ($membership === null || $membership->status !== MembershipStatus::Active || ! app(Concierge::class)->isOwner($membership)) {
            Response::deny('Only the workspace owner can delete it.', 'not_owner')->authorize();
        }
    }

    /** @return list<string> the tables keyed by `workspace_id` of the features that are on, children first (the notes tables are the app's once it adopted notes) */
    protected function featureTables(): array
    {
        return array_keys(self::owners());
    }

    /**
     * Each table keyed by `workspace_id` of a feature that is on, children first => the feature that owns it. A domain table (Kit::table()) is named
     * only for a feature that is on: the feature registers it.
     *
     * @return array<string, string>
     */
    private static function owners(): array
    {
        $tables = [];
        foreach ([['notes', 'note_intent', true], ['notes', 'note', true], ['mcp', 'connections', false], ['settings', 'settings_intents', false], ['access', 'complimentary_grant', true], ['access', 'entitlement', true], ['access', 'plan_entitlements', false], ['access', 'capability_overrides', false], ['session-ui', 'user_state', false]] as [$feature, $table, $domain]) {
            if (Kit::enabled($feature)) {
                $tables[$domain ? Kit::table($table) : $table] = $feature;
            }
        }

        return $tables;
    }

    /**
     * @internal The kit's and Concierge's rows for the workspace, children first (FinishWorkspaceDeletion's too). A table of a feature that is off is
     * skipped; a missing table of a feature that is on throws, so the deletion rolls back rather than leave that feature's rows behind (#967).
     */
    public function deleteRows(int $id): void
    {
        $owners = self::owners();
        // A feature's own table must have the column it is deleted by, or its rows would stay behind, and so must a table $required names (the
        // permission tables' team column, with teams on). Only another table, such as the permission tables with teams off, may lack it.
        $where = function (string $table, string $column = 'workspace_id', bool $required = false) use ($owners, $id): int {
            if (! Kit::present($owners[$table] ?? 'workspaces', $table)) {
                return 0;
            }
            if (! Schema::hasColumn($table, $column)) {
                if (isset($owners[$table]) || $required) {
                    throw new RuntimeException('The '.($owners[$table] ?? 'workspaces').' feature is on but its table '.$table.' has no '.$column.' column, so nothing was deleted. Restore the column, then delete the workspace again.');
                }

                return 0;
            }

            return DB::table($table)->where($column, $id)->delete();
        };
        if (Kit::present('mcp', 'connections') && Kit::present('mcp', 'mcp_intents')) {
            DB::table('mcp_intents')->whereIn('connection_id', DB::table('connections')->where('workspace_id', $id)->select('id'))->delete();
        }
        // Every installed feature's rows go here, explicitly, before the workspace row. On RESTRICT foreign keys: notes (`kit_note_intents`, `kit_notes`), mcp
        // (`connections`, whose `mcp_intents` cascade and were cleared above), settings (`settings_intents`) and access's commercial records
        // (`complimentary_grants`, `entitlements`). `plan_entitlements` and `capability_overrides` cascade, and are cleared too for a connection that does not
        // enforce foreign keys. Each table is guarded by its feature: a feature that is off is skipped, one that is on must have its table.
        foreach ($this->featureTables() as $table) {
            $where($table);
        }
        $team = (string) config('permission.column_names.team_foreign_key', 'workspace_id');
        foreach (['model_has_roles', 'model_has_permissions', 'roles'] as $key) {
            $where((string) config('permission.table_names.'.$key, $key), $team, (bool) config('permission.teams'));
        }
        $settings = (string) (config('settings.repositories.database.table') ?? 'settings');
        if (Kit::present('settings', $settings)) {
            DB::table($settings)->where('group', 'workspace.'.$id)->delete();
        }
        foreach ([ConciergeModels::invitation(), ConciergeModels::tenantInvitation(), ConciergeModels::ownership(), ConciergeModels::membership()] as $model) {
            $where((new $model)->getTable());
        }
    }
}

<?php

namespace Tey\Kit\Features\SessionUi\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Tey\Concierge\Actions\LeaveWorkspace;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\SessionUi\Events\AccountDeleted;
use Tey\Kit\Features\SessionUi\Events\AccountDeleting;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Deletes the signed-in person's account, with their current password. Refused while they own any workspace: Concierge keeps exactly one owner per workspace,
 * so its owner is always its sole owner, and they transfer ownership or delete the workspace first. Otherwise each membership still open is ended through
 * Concierge's LeaveWorkspace (its roles cleared, MembershipRemoved dispatched), then the membership rows, the Concierge account row and the kit's per-user rows
 * go with the user row, in one transaction. AccountDeleting is dispatched inside it, AccountDeleted after the commit. Signing out is the caller's.
 */
class DeleteAccount
{
    use AsAction;

    /** @return list<int> the workspaces the user left */
    public function handle(Model $user, string $password): array
    {
        if (! Hash::check($password, (string) $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => 'That password is not right.']);
        }
        if (($owned = self::ownedWorkspaces($user)) !== []) {
            throw ValidationException::withMessages(['account' => 'You own '.implode(', ', $owned).'. Transfer ownership or delete '.(count($owned) === 1 ? 'that workspace' : 'those workspaces').' first.']);
        }

        return DB::transaction(function () use ($user): array {
            $id = (int) $user->getKey();
            // Checked again inside the transaction: an ownership handed to them meanwhile refuses the deletion.
            if (self::ownedWorkspaces($user) !== []) {
                throw ValidationException::withMessages(['account' => 'You own a workspace. Transfer ownership or delete it first.']);
            }
            $memberships = ConciergeModels::membership()::query()->where('user_id', $id)->get();
            $left = [];
            foreach ($memberships as $membership) {
                if ($membership->status === MembershipStatus::Removed) {
                    continue;
                }
                LeaveWorkspace::run($user, Workspaces::query()->findOrFail($membership->workspace_id));
                $left[] = (int) $membership->workspace_id;
            }
            sort($left);

            AccountDeleting::dispatch($id, $left);

            $membershipIds = $memberships->modelKeys();
            if ($membershipIds !== []) {
                // Set null by hand as well as by the foreign keys, for a connection that does not enforce them.
                DB::table((new (ConciergeModels::ownership()))->getTable())->whereIn('previous_membership_id', $membershipIds)->update(['previous_membership_id' => null]);
                DB::table((new (ConciergeModels::invitation()))->getTable())->whereIn('accepted_membership_id', $membershipIds)->update(['accepted_membership_id' => null]);
                ConciergeModels::membership()::query()->whereKey($membershipIds)->delete();
            }
            ConciergeModels::account()::query()->where('user_id', $id)->delete();
            foreach (['kit_capability_overrides' => 'user_id', 'kit_user_state' => 'user_id', 'kit_security_events' => 'user_id'] as $table => $column) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where($column, $id)->delete();
                }
            }
            if (Schema::hasTable('connections')) {
                if (Schema::hasTable('kit_mcp_intents')) {
                    DB::table('kit_mcp_intents')->whereIn('connection_id', DB::table('connections')->where('actor_id', $id)->select('id'))->delete();
                }
                DB::table('connections')->where('actor_id', $id)->delete();
            }
            foreach (['model_has_roles', 'model_has_permissions'] as $key) {
                $table = (string) config('permission.table_names.'.$key, $key);
                if (Schema::hasTable($table)) {
                    DB::table($table)->where(config('permission.column_names.model_morph_key', 'model_id'), $id)->where('model_type', $user->getMorphClass())->delete();
                }
            }
            $user->delete();

            AccountDeleted::dispatch($id, $left);

            return $left;
        });
    }

    /** @return list<string> the names of the workspaces the user owns, by name */
    public static function ownedWorkspaces(Model $user): array
    {
        $memberships = (new (ConciergeModels::membership()))->getTable();
        $ownerships = (new (ConciergeModels::ownership()))->getTable();
        $ids = DB::table($ownerships)->join($memberships, $memberships.'.id', '=', $ownerships.'.membership_id')->where($memberships.'.user_id', $user->getKey())->pluck($ownerships.'.workspace_id')->all();

        return $ids === [] ? [] : Workspaces::query()->whereKey($ids)->orderBy('name')->pluck('name')->map(fn ($name): string => (string) $name)->all();
    }
}

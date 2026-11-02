<?php

namespace Tey\Kit\Features\SessionUi\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Tey\Concierge\Actions\LeaveWorkspace;
use Tey\Concierge\Enums\AccountStatus;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\UserSessions;
use Tey\Kit\Features\SessionUi\Contracts\DefersAccountPurge;
use Tey\Kit\Features\SessionUi\Contracts\PurgesAccountData;
use Tey\Kit\Features\SessionUi\Deletions;
use Tey\Kit\Features\SessionUi\Events\AccountDeleted;
use Tey\Kit\Features\SessionUi\Events\AccountDeleting;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Deletes the signed-in person's account, with their current password. Refused while they own any workspace: Concierge keeps exactly one owner per workspace,
 * so its owner is always its sole owner, and they transfer ownership or delete the workspace first. Otherwise each membership still open is ended through
 * Concierge's LeaveWorkspace (its roles cleared, MembershipRemoved dispatched), then the membership rows, the Concierge account row and the kit's per-user rows
 * go with the user row, in one transaction. AccountDeleting is dispatched inside it, AccountDeleted after the commit. Signing out is the caller's.
 *
 * Off unless `kit.lifecycle.account_delete` is on AND a PurgesAccountData handler is bound: otherwise this refuses before anything, nothing deleted. The handler
 * runs inside the transaction, after the workspaces are left and before the kit's rows and the user row go.
 *
 * Deferred, when the bound handler implements DefersAccountPurge: the workspaces are left and the account closed at once (its Concierge account deactivated, so
 * sign-in is refused; database sessions ended, the remember-me token cycled, API tokens revoked; a `kit_account_deletions` row recorded), AccountDeleting
 * dispatched, then after the commit the handler's begin() queues the host's job, which ends by calling FinishAccountDeletion. Nothing else is deleted here.
 */
class DeleteAccount
{
    use AsAction;

    /** @return list<int> the workspaces the user left */
    public function handle(Model $user, string $password): array
    {
        /** @var PurgesAccountData $purger */
        $purger = Deletions::handler(Deletions::ACCOUNT);
        if (! Hash::check($password, (string) $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => 'That password is not right.']);
        }
        if (($owned = self::ownedWorkspaces($user)) !== []) {
            throw ValidationException::withMessages(['account' => 'You own '.implode(', ', $owned).'. Transfer ownership or delete '.(count($owned) === 1 ? 'that workspace' : 'those workspaces').' first.']);
        }

        if ($purger instanceof DefersAccountPurge) {
            return $this->close($user, $purger);
        }

        return DB::transaction(function () use ($user, $purger): array {
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
            // The host's own data first, while the rows it may reference still exist; a throw rolls everything back.
            $purger->purge($user);

            self::deleteRows($user, $memberships->modelKeys());

            AccountDeleted::dispatch($id, $left);

            return $left;
        });
    }

    /** The deferred mode: leaves the workspaces and closes the account now; the host's job purges, then calls FinishAccountDeletion. */
    private function close(Model $user, DefersAccountPurge $purger): array
    {
        if (! Schema::hasTable('kit_account_deletions')) {
            throw new RuntimeException('Deferred account deletion needs the kit_account_deletions table: run `php artisan kit:update apply`, then migrate. Nothing was deleted.');
        }
        $left = DB::transaction(function () use ($user): array {
            $id = (int) $user->getKey();
            if (self::ownedWorkspaces($user) !== []) {
                throw ValidationException::withMessages(['account' => 'You own a workspace. Transfer ownership or delete it first.']);
            }
            if (DB::table('kit_account_deletions')->where('user_id', $id)->exists()) {
                return json_decode((string) DB::table('kit_account_deletions')->where('user_id', $id)->value('workspace_ids'), true, 512, JSON_THROW_ON_ERROR);
            }
            $left = $this->leave($user);
            AccountDeleting::dispatch($id, $left);
            ConciergeModels::account()::query()->updateOrCreate(['user_id' => $id], ['status' => AccountStatus::Deactivated, 'deactivated_at' => now(), 'deactivated_by_id' => $id, 'deactivation_reason' => 'Account deletion in progress.']);
            DB::table('kit_account_deletions')->insert(['user_id' => $id, 'workspace_ids' => json_encode($left, JSON_THROW_ON_ERROR), 'started_at' => now()]);
            // Closed at once: no session or token outlives the request.
            if (($remember = $user->getRememberTokenName()) && Schema::hasColumn($user->getTable(), $remember)) {
                UserSessions::cycleRememberToken($user);
                $user->save();
            }
            UserSessions::endDatabaseSessions($user);
            // API tokens, by table: Passport's are revoked (and their refresh tokens), Sanctum's deleted.
            if (Schema::hasTable('oauth_access_tokens')) {
                $tokens = DB::table('oauth_access_tokens')->where('user_id', $id);
                if (Schema::hasTable('oauth_refresh_tokens')) {
                    DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokens->clone()->select('id'))->update(['revoked' => true]);
                }
                $tokens->update(['revoked' => true]);
            }
            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $id)->delete();
            }

            return $left;
        });
        // After the commit and outside the kit's transaction. A throw here leaves the account closed, never reopened.
        $purger->begin($user);

        return $left;
    }

    /** Whether the bound account purge handler defers (DefersAccountPurge). */
    public static function deferred(): bool
    {
        return Deletions::handler(Deletions::ACCOUNT) instanceof DefersAccountPurge;
    }

    /** @return list<int> each membership still open, ended through Concierge's LeaveWorkspace, by workspace id */
    private function leave(Model $user): array
    {
        $left = [];
        foreach (ConciergeModels::membership()::query()->where('user_id', $user->getKey())->get() as $membership) {
            if ($membership->status === MembershipStatus::Removed) {
                continue;
            }
            LeaveWorkspace::run($user, Workspaces::query()->findOrFail($membership->workspace_id));
            $left[] = (int) $membership->workspace_id;
        }
        sort($left);

        return $left;
    }

    /**
     * @internal The kit's and Concierge's rows for the person, then the user row (FinishAccountDeletion's too).
     *
     * @param  list<int|string>  $membershipIds
     */
    public static function deleteRows(Model $user, array $membershipIds): void
    {
        $id = (int) $user->getKey();
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

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
use Tey\Kit\Features\SessionUi\Account\AnonymiseAccountRow;
use Tey\Kit\Features\SessionUi\Contracts\DefersAccountPurge;
use Tey\Kit\Features\SessionUi\Contracts\DeletesAccountRow;
use Tey\Kit\Features\SessionUi\Contracts\PurgesAccountData;
use Tey\Kit\Features\SessionUi\Deletions;
use Tey\Kit\Features\SessionUi\Events\AccountDeleted;
use Tey\Kit\Features\SessionUi\Events\AccountDeleting;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Deletes the signed-in person's account, with their current password. Refused while they own any workspace: Concierge keeps exactly one owner per workspace,
 * so its owner is always its sole owner, and they transfer ownership or delete the workspace first. Otherwise each membership still open is ended through
 * Concierge's LeaveWorkspace (its roles cleared, MembershipRemoved dispatched), then the membership rows, the Concierge account row and the kit's per-user rows
 * go with the user row, in one transaction; sessions, the remember-me token and API tokens end with it, and a kept (anonymised) row's Concierge
 * account stays deactivated. AccountDeleting is dispatched inside it, AccountDeleted after the commit. Signing out is the caller's.
 *
 * Off unless `kit.lifecycle.account_delete` is on AND a PurgesAccountData handler is bound: otherwise this refuses before anything, nothing deleted. The handler
 * runs inside the transaction, after the workspaces are left and before the kit's rows and the user row go.
 *
 * Deferred, when the bound handler implements DefersAccountPurge: the workspaces are left and the account closed at once (its Concierge account deactivated, so
 * sign-in is refused; database sessions ended, the remember-me token cycled, API tokens revoked; a `account_deletions` row recorded), AccountDeleting
 * dispatched, then after the commit the handler's begin() queues the host's job, which ends by calling FinishAccountDeletion. Nothing else is deleted here.
 */
class DeleteAccount
{
    use AsAction;

    /** @return list<int> the workspaces the user left */
    public function handle(Model $user, ?string $password): array
    {
        /** @var PurgesAccountData $purger */
        $purger = Deletions::handler(Deletions::ACCOUNT);
        // Unless kit.lifecycle.confirm_password is false exactly (fails closed: a missing password is refused).
        if (Deletions::confirmsPassword() && ($password === null || ! Hash::check($password, (string) $user->getAuthPassword()))) {
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

            self::endAccess($user);
            self::deleteRows($user, $memberships->modelKeys());
            if (ConciergeModels::user()::query()->whereKey($id)->exists()) {
                // The row was kept (anonymised): it stays closed, so nothing can sign in as it.
                ConciergeModels::account()::query()->updateOrCreate(['user_id' => $id], ['status' => AccountStatus::Deactivated, 'deactivated_at' => now(), 'deactivated_by_id' => $id, 'deactivation_reason' => 'Account deleted.']);
            }

            AccountDeleted::dispatch($id, $left);

            return $left;
        });
    }

    /** The deferred mode: leaves the workspaces and closes the account now; the host's job purges, then calls FinishAccountDeletion. */
    private function close(Model $user, DefersAccountPurge $purger): array
    {
        if (! Schema::hasTable('account_deletions')) {
            throw new RuntimeException('Deferred account deletion needs the account_deletions table: run `php artisan migrate`. Nothing was deleted.');
        }
        $left = DB::transaction(function () use ($user): array {
            $id = (int) $user->getKey();
            if (self::ownedWorkspaces($user) !== []) {
                throw ValidationException::withMessages(['account' => 'You own a workspace. Transfer ownership or delete it first.']);
            }
            if (DB::table('account_deletions')->where('user_id', $id)->exists()) {
                return json_decode((string) DB::table('account_deletions')->where('user_id', $id)->value('workspace_ids'), true, 512, JSON_THROW_ON_ERROR);
            }
            $left = $this->leave($user);
            AccountDeleting::dispatch($id, $left);
            ConciergeModels::account()::query()->updateOrCreate(['user_id' => $id], ['status' => AccountStatus::Deactivated, 'deactivated_at' => now(), 'deactivated_by_id' => $id, 'deactivation_reason' => 'Account deletion in progress.']);
            DB::table('account_deletions')->insert(['user_id' => $id, 'workspace_ids' => json_encode($left, JSON_THROW_ON_ERROR), 'started_at' => now()]);
            // Closed at once: no session or token outlives the request.
            self::endAccess($user);

            return $left;
        });
        // After the commit and outside the kit's transaction. A throw here leaves the account closed, never reopened.
        $purger->begin($user);

        return $left;
    }

    /** Ends every way back in: the remember-me token cycled, database sessions ended, Passport tokens (and their refresh tokens) revoked, Sanctum's deleted. */
    private static function endAccess(Model $user): void
    {
        $id = (int) $user->getKey();
        if (($remember = $user->getRememberTokenName()) && Schema::hasColumn($user->getTable(), $remember)) {
            UserSessions::cycleRememberToken($user);
            $user->save();
        }
        UserSessions::endDatabaseSessions($user);
        // API tokens, by table: Passport's are revoked (with their refresh tokens and any unexchanged codes), Sanctum's deleted.
        if (Schema::hasTable('oauth_access_tokens')) {
            $tokens = DB::table('oauth_access_tokens')->where('user_id', $id);
            if (Schema::hasTable('oauth_refresh_tokens')) {
                DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokens->clone()->select('id'))->update(['revoked' => true]);
            }
            $tokens->update(['revoked' => true]);
        }
        // Codes not yet exchanged for a token would still exchange within their lifetime: an authorization code, and a device code the person approved.
        foreach (['oauth_auth_codes', 'oauth_device_codes'] as $codes) {
            if (Schema::hasTable($codes)) {
                DB::table($codes)->where('user_id', $id)->update(['revoked' => true]);
            }
        }
        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $id)->delete();
        }
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
        foreach (['capability_overrides' => 'user_id', 'user_state' => 'user_id', 'kit_security_events' => 'user_id'] as $table => $column) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where($column, $id)->delete();
            }
        }
        if (Schema::hasTable('connections')) {
            if (Schema::hasTable('mcp_intents')) {
                DB::table('mcp_intents')->whereIn('connection_id', DB::table('connections')->where('actor_id', $id)->select('id'))->delete();
            }
            DB::table('connections')->where('actor_id', $id)->delete();
        }
        foreach (['model_has_roles', 'model_has_permissions'] as $key) {
            $table = (string) config('permission.table_names.'.$key, $key);
            if (Schema::hasTable($table)) {
                DB::table($table)->where(config('permission.column_names.model_morph_key', 'model_id'), $id)->where('model_type', $user->getMorphClass())->delete();
            }
        }
        self::deleteUserRow($user);
    }

    /**
     * The user row itself: through the host's DeletesAccountRow when bound (it may anonymise instead); else the kit's AnonymiseAccountRow when
     * `kit.lifecycle.account_user_row` is `anonymise`; else a hard delete.
     */
    public static function deleteUserRow(Model $user): void
    {
        if (app()->bound(DeletesAccountRow::class)) {
            app(DeletesAccountRow::class)->delete($user);

            return;
        }
        if (Deletions::userRow() === 'anonymise') {
            app(AnonymiseAccountRow::class)->delete($user);

            return;
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

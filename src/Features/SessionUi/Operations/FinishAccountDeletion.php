<?php

namespace Tey\Kit\Features\SessionUi\Operations;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tey\Concierge\Enums\AccountStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\SessionUi\Events\AccountDeleted;

/**
 * The end of a deferred account deletion: the host's job calls `app(FinishAccountDeletion::class)($userId)` once its own data is gone. It deletes the kit's and
 * Concierge's rows for the person and the user row (through DeletesAccountRow when bound: a host may anonymise it), in one short transaction, and
 * dispatches AccountDeleted after the commit. An anonymised row keeps its Concierge account deactivated and its record marked finished.
 *
 * Idempotent and retry-safe: a user already gone, or an account already finished (anonymised), is a no-op (false). Fails closed: a user whose account is not being deleted (no `kit_account_deletions` row) is
 * refused with `account_not_deleting` and nothing is deleted.
 */
final class FinishAccountDeletion
{
    /** @return bool true when this call deleted the account, false when it was already gone */
    public function __invoke(int $userId): bool
    {
        return DB::transaction(function () use ($userId): bool {
            $marker = DB::table('kit_account_deletions')->where('user_id', $userId)->lockForUpdate()->first();
            if ($marker !== null && ($marker->finished_at ?? null) !== null) {
                return false;
            }
            $user = ConciergeModels::user()::query()->find($userId);
            if ($marker === null) {
                if ($user === null) {
                    return false;
                }
                throw new RuntimeException('account_not_deleting');
            }
            if ($user === null) {
                DB::table('kit_account_deletions')->where('user_id', $userId)->delete();

                return false;
            }
            DeleteAccount::deleteRows($user, ConciergeModels::membership()::query()->where('user_id', $userId)->pluck('id')->all());
            if (ConciergeModels::user()::query()->whereKey($userId)->exists()) {
                // The host kept the row (anonymised): it stays closed, and the record stays, marked finished.
                ConciergeModels::account()::query()->updateOrCreate(['user_id' => $userId], ['status' => AccountStatus::Deactivated, 'deactivated_at' => now(), 'deactivated_by_id' => $userId, 'deactivation_reason' => 'Account deleted.']);
                DB::table('kit_account_deletions')->where('user_id', $userId)->update(['finished_at' => now()]);
            } else {
                DB::table('kit_account_deletions')->where('user_id', $userId)->delete();
            }
            // ShouldDispatchAfterCommit: held until the transaction commits, dropped on a rollback.
            AccountDeleted::dispatch($userId, array_values(array_map('intval', json_decode((string) $marker->workspace_ids, true, 512, JSON_THROW_ON_ERROR))));

            return true;
        });
    }
}

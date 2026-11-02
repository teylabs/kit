<?php

namespace Tey\Kit\Features\SessionUi\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Opts a host's account purge handler into deferred deletion. Bound as PurgesAccountData, a handler that also implements this is never run in the request: the
 * kit leaves the person's workspaces, closes the account at once (its Concierge account deactivated, so sign-in is refused; database sessions and API tokens
 * revoked) and then calls begin(), outside any transaction of the kit's, for the host to queue its own job. That job purges the host's data and, last, calls
 * `app(FinishAccountDeletion::class)($userId)`, which deletes the kit's rows and the user row in one short transaction. purge() is not called by the kit in this
 * mode. A closed account is never reopened by the kit. See docs/lifecycle-events.md.
 */
interface DefersAccountPurge extends PurgesAccountData
{
    public function begin(Model $user): void;
}

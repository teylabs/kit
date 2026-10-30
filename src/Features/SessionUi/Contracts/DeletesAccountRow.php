<?php

namespace Tey\Kit\Features\SessionUi\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * How an account deletion removes the user row itself, after the kit's and Concierge's rows for the person are gone. Unbound, the kit hard-deletes it
 * (`$user->delete()`). A host whose own tables keep history on RESTRICT foreign keys to users binds its own, which may anonymise the row instead of deleting it.
 * Runs inside the deletion's transaction; throwing rolls it back. An anonymised row stays closed: the kit keeps its Concierge account deactivated, so it
 * never signs in, and FinishAccountDeletion treats it as finished.
 */
interface DeletesAccountRow
{
    public function delete(Model $user): void;
}

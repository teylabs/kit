<?php

namespace Tey\Kit\Features\SessionUi\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * The host's purge of its own data for an account being deleted. Required to turn `kit.lifecycle.account_delete` on: without a binding the deletion refuses.
 * Runs inside the deletion's transaction, after the person has left their workspaces and BEFORE the kit's rows and the user row go; throwing rolls it all back.
 * A host with nothing of its own to purge binds a handler that does nothing, explicitly.
 */
interface PurgesAccountData
{
    public function purge(Model $user): void;
}

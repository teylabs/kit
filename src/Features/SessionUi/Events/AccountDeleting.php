<?php

namespace Tey\Kit\Features\SessionUi\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An account is about to be deleted: dispatched inside the deletion's transaction, after its memberships were ended and before the user row goes. Informational
 * only: there is no veto, and a listener must not queue work (the transaction may still roll back). A host purges on AccountDeleted, after the commit.
 */
final class AccountDeleting
{
    use Dispatchable;

    /** @param  list<int>  $workspaceIds  the workspaces the user was an active or suspended member of, and left */
    public function __construct(public readonly int $userId, public readonly array $workspaceIds) {}
}

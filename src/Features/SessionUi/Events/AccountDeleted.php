<?php

namespace Tey\Kit\Features\SessionUi\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An account was deleted. Dispatched only after the deletion's transaction commits (ShouldDispatchAfterCommit): never for a rollback, and never before the user
 * row is gone. A queued purge listener works from these ids; the user can no longer be loaded. See docs/lifecycle-events.md.
 */
final class AccountDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param  list<int>  $workspaceIds  the workspaces the user was an active or suspended member of, and left */
    public function __construct(public readonly int $userId, public readonly array $workspaceIds) {}
}

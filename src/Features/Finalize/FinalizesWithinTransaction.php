<?php

namespace Tey\Kit\Features\Finalize;

use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Finalize\Models\Finalization;

/**
 * For a subject model that must change its own state atomically with finalizing and un-finalizing (bump a guard the flow's `lockColumn()` does not
 * cover, mark a related record, say). The gate calls these inside its transaction, after the subject's row lock and the kit's own writes, before the
 * commit: a write here commits or rolls back with the finalization, and an exception rolls the whole operation back, so `Finalized` and
 * `Unfinalized` never fire. A subject that does not implement it finalizes exactly as before.
 */
interface FinalizesWithinTransaction
{
    /**
     * After the finalization row is written and the lock column bumped, before the flow's extras `apply`.
     *
     * @param  array<string, mixed>  $extras  the flow's validated declared extras, as on `Finalized::$extras`; empty without them
     */
    public function onFinalizing(string $flow, Finalization $finalization, Principal $principal, array $extras): void;

    /** After the finalization row is marked un-finalized and the lock column bumped. */
    public function onUnfinalizing(string $flow, Finalization $finalization, Principal $principal): void;
}

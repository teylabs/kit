<?php

namespace Tey\Kit\Features\Deliver;

use Illuminate\Database\Eloquent\Model;

/**
 * For an app that finalizes through its own model rather than a kit finalize flow (an `assignments.finalized_at` column, say). Passed to a delivery's
 * `finalizationRef()`, it names what is final as of now; each send records it as `finalization_ref`, and the history calls a delivery "no longer
 * final" once the current ref differs. Without one, a delivery reads `kit_finalizations` exactly as before.
 */
interface ResolvesFinalization
{
    /** A stable token for the subject's finalization as it is now (its id and finalized_at, say), or null while it is not final. */
    public function finalizationRef(Model $subject): ?string;
}

<?php

namespace Tey\Kit\Features\Finalize\Operations;

use RuntimeException;

/** The flow's own un-finalize rule refused (a second person is needed, say). The code is `finalize_unfinalize_refused`; `reason` is the flow's words. */
final class UnfinalizeRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('finalize_unfinalize_refused');
    }
}

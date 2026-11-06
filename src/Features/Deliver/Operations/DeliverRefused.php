<?php

namespace Tey\Kit\Features\Deliver\Operations;

use RuntimeException;

/** A send refused for a reason a person can act on: the gate's words ("Finalize it first."), or a recipient who is no longer a contact. */
final class DeliverRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, string $code = 'deliver_gated')
    {
        parent::__construct($code);
    }
}

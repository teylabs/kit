<?php

namespace Tey\Kit\Features\Deliver\Events;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Deliver\Contracts\Delivery;

/** Dispatched when a scheduled delivery is cancelled, by a person (automatic false) or by the kit (automatic true: the gate, the version, the sender's access or a recipient changed, or the subject was un-finalized). An app tells the owner and the creator from here. */
final class DeliveryCancelled
{
    public function __construct(public readonly string $definition, public readonly Model&Delivery $delivery, public readonly string $reason, public readonly bool $automatic) {}
}

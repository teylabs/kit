<?php

namespace Tey\Kit\Features\Deliver\Events;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Deliver\Contracts\Delivery;
use Tey\Kit\Features\Deliver\Contracts\DeliveryRecipient;

/** Dispatched when the provider reports a bounce or complaint for one address. An app tells the sender from here. */
final class DeliveryBounced
{
    public function __construct(public readonly string $definition, public readonly Model&Delivery $delivery, public readonly Model&DeliveryRecipient $recipient) {}
}

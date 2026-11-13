<?php

namespace Tey\Kit\Features\Deliver\Events;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Deliver\Contracts\Delivery;
use Tey\Kit\Features\Deliver\Contracts\DeliveryRecipient;

/**
 * Dispatched after commit, once per address, on the first "delivered" the provider reports for it: never for a replayed webhook, a second "delivered",
 * or after a bounce or complaint. The subject is `$delivery->definition` and `$delivery->subject_key`. A webhook has no actor.
 */
final class DeliveryDelivered
{
    public function __construct(public readonly string $definition, public readonly Model&Delivery $delivery, public readonly Model&DeliveryRecipient $recipient) {}
}

<?php

namespace Tey\Kit\Features\Deliver\Events;

use Tey\Kit\Features\Deliver\Models\DeliveryRecipient;
use Tey\Kit\Features\Deliver\Models\DeliveryRecord;

/**
 * Dispatched after commit, once per address, on the first "delivered" the provider reports for it: never for a replayed webhook, a second "delivered",
 * or after a bounce or complaint. The subject is `$delivery->definition` and `$delivery->subject_key`. A webhook has no actor.
 */
final class DeliveryDelivered
{
    public function __construct(public readonly string $definition, public readonly DeliveryRecord $delivery, public readonly DeliveryRecipient $recipient) {}
}

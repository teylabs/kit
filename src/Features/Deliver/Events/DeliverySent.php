<?php

namespace Tey\Kit\Features\Deliver\Events;

use Tey\Kit\Features\Deliver\Models\DeliveryRecord;

/** Dispatched after a delivery leaves, once the provider took it. */
final class DeliverySent
{
    public function __construct(public readonly string $definition, public readonly DeliveryRecord $delivery) {}
}

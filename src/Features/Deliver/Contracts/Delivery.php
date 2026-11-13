<?php

namespace Tey\Kit\Features\Deliver\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A delivery, as the kit reads and writes it: the model `kit.models.delivery` names, which uses Concerns\IsDelivery. The kit's own is
 * Models\DeliveryRecord (`kit_deliveries`); an app that adopted deliver has its own (`deliveries`).
 */
interface Delivery
{
    /** @return HasMany<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model> */
    public function recipients(): HasMany;
}

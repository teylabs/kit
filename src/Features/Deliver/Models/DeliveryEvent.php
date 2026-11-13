<?php

namespace Tey\Kit\Features\Deliver\Models;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Deliver\Concerns\IsDeliveryEvent;
use Tey\Kit\Features\Deliver\Contracts\DeliveryEvent as DeliveryEventContract;

/**
 * The kit's delivery event model, on `kit_delivery_events`: the default for `kit.models.delivery_event` until the app adopts deliver.
 * Deprecated since 0.19 for new code: reach it through `Tey\Kit\Kit::model('delivery_event')`. The behaviour is Concerns\IsDeliveryEvent.
 */
class DeliveryEvent extends Model implements DeliveryEventContract
{
    use IsDeliveryEvent;

    protected $table = 'kit_delivery_events';
}

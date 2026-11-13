<?php

namespace Tey\Kit\Features\Deliver\Models;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Deliver\Concerns\IsDelivery;
use Tey\Kit\Features\Deliver\Contracts\Delivery;

/**
 * The kit's delivery model, on `kit_deliveries`: the default for `kit.models.delivery` until the app adopts deliver (`kit:scaffold deliver --adopt`),
 * which gives it its own model on `deliveries`. Deprecated since 0.19 for new code: reach the delivery model through `Tey\Kit\Features\Core\Kit::model('delivery')`.
 * The behaviour is Concerns\IsDelivery.
 */
class DeliveryRecord extends Model implements Delivery
{
    use IsDelivery;

    protected $table = 'kit_deliveries';
}

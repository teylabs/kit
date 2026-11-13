<?php

namespace Tey\Kit\Features\Deliver\Models;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Deliver\Concerns\IsDeliveryRecipient;
use Tey\Kit\Features\Deliver\Contracts\DeliveryRecipient as DeliveryRecipientContract;

/**
 * The kit's delivery recipient model, on `kit_delivery_recipients`: the default for `kit.models.delivery_recipient` until the app adopts deliver.
 * Deprecated since 0.19 for new code: reach it through `Tey\Kit\Kit::model('delivery_recipient')`. The behaviour is Concerns\IsDeliveryRecipient.
 */
class DeliveryRecipient extends Model implements DeliveryRecipientContract
{
    use IsDeliveryRecipient;

    protected $table = 'kit_delivery_recipients';
}

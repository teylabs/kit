<?php

namespace Tey\Kit\Features\Deliver\Events;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Deliver\Contracts\Delivery;
use Tey\Kit\Features\Deliver\Contracts\DeliveryRecipient;

/**
 * Dispatched after commit, once per delivery, when the customer link's first open sets `first_opened_at` (a conditional update, so two opens at once
 * dispatch one). `$recipient` is the address that opened it when the open identifies one; the link is per delivery, so today it is null. The subject is
 * `$delivery->definition` and `$delivery->subject_key`. A customer open has no actor.
 */
final class DeliveryOpened
{
    public function __construct(public readonly string $definition, public readonly Model&Delivery $delivery, public readonly (Model&DeliveryRecipient)|null $recipient = null) {}
}

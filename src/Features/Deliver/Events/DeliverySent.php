<?php

namespace Tey\Kit\Features\Deliver\Events;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Deliver\Contracts\Delivery;

/** Dispatched after a delivery leaves, once the provider took it. A resend is a new delivery: `$delivery->resent_from_id` is the original's id, and resent() says so. */
final class DeliverySent
{
    public function __construct(public readonly string $definition, public readonly Model&Delivery $delivery) {}

    /** Whether this delivery is a resend of an earlier one (`$delivery->resent_from_id`). */
    public function resent(): bool
    {
        return $this->delivery->resent_from_id !== null;
    }
}

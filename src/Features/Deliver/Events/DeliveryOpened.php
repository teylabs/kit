<?php

namespace Tey\Kit\Features\Deliver\Events;

use Tey\Kit\Features\Deliver\Models\DeliveryRecipient;
use Tey\Kit\Features\Deliver\Models\DeliveryRecord;

/**
 * Dispatched after commit, once per delivery, when the customer link's first open sets `first_opened_at` (a conditional update, so two opens at once
 * dispatch one). `$recipient` is the address that opened it when the open identifies one; the link is per delivery, so today it is null. The subject is
 * `$delivery->definition` and `$delivery->subject_key`. A customer open has no actor.
 */
final class DeliveryOpened
{
    public function __construct(public readonly string $definition, public readonly DeliveryRecord $delivery, public readonly ?DeliveryRecipient $recipient = null) {}
}

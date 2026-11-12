<?php

namespace Tey\Kit\Features\Deliver\Events;

use Tey\Kit\Features\Deliver\Models\DeliveryRecord;

/**
 * Dispatched after commit whenever a delivery's customer link is revoked (`token_revoked_at` set): by Revoke link (`resent` false) or by a resend that
 * revokes the original (`resent` true; `$delivery` is the original). `$actorId` is who did it, null for a revoke by the kit. The subject is
 * `$delivery->definition` and `$delivery->subject_key`.
 */
final class DeliveryLinkRevoked
{
    public function __construct(public readonly string $definition, public readonly DeliveryRecord $delivery, public readonly ?int $actorId, public readonly bool $resent) {}
}

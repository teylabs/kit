<?php

namespace Tey\Kit\Features\Deliver;

/**
 * What the app's Notification is built from, the same for the preview and the send. `link` is the customer's signed link, or null in a preview and a
 * test send (the button then goes nowhere); `attachment` is `attached`, `linked` (too large to attach: the link carries it) or `none`.
 */
final class DeliveryMail
{
    public function __construct(
        public readonly string $subject,
        public readonly string $message,
        public readonly ?string $link,
        public readonly string $attachment,
        public readonly bool $test = false,
        public readonly bool $preview = false,
    ) {}
}

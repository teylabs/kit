<?php

namespace Tey\Kit\Features\Deliver;

/** The document a delivery carries: its file name, MIME type and bytes, rendered by the app. */
final class DeliveryAttachment
{
    public function __construct(public readonly string $filename, public readonly string $mime, public readonly string $contents) {}
}

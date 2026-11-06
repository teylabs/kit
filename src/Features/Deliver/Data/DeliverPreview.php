<?php

namespace Tey\Kit\Features\Deliver\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The email exactly as it would be sent: subject line and HTML from the app's own Notification, and what happens to the document. Draw `html` only in a
 * sandboxed frame (the kit's DeliverPreview does): it is an email, not part of the page.
 */
#[TypeScript]
final class DeliverPreview extends Data
{
    public function __construct(
        public string $subject,
        public string $html,
        #[LiteralTypeScriptType("'attached' | 'linked' | 'none'")] public string $attachment,
        public ?string $attachmentName,
        public ?int $attachmentBytes,
    ) {}
}

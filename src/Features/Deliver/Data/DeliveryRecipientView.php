<?php

namespace Tey\Kit\Features\Deliver\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One address on a delivery and what the provider last said about it. */
#[TypeScript]
final class DeliveryRecipientView extends Data
{
    public function __construct(
        #[LiteralTypeScriptType("'to' | 'cc' | 'bcc'")] public string $role,
        public string $address,
        public ?string $name,
        #[LiteralTypeScriptType("'pending' | 'sent' | 'delivered' | 'bounced' | 'complained'")] public string $status,
    ) {}
}

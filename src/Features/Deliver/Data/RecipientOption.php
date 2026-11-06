<?php

namespace Tey\Kit\Features\Deliver\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A person a send may go to, from the app's live records, and whether they default to To or Cc. */
#[TypeScript]
final class RecipientOption extends Data
{
    public function __construct(
        public string $address,
        public ?string $name,
        #[LiteralTypeScriptType("'to' | 'cc'")] public string $role,
    ) {}
}

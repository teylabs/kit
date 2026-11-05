<?php

namespace Tey\Kit\Features\Finalize\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One reason a finalize step does not hold, with the field it concerns and a link to the fix when there is one. */
#[TypeScript]
final class GateError extends Data
{
    public function __construct(public string $message, public ?string $field = null, public ?string $url = null) {}
}

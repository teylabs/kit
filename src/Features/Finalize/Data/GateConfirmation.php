<?php

namespace Tey\Kit\Features\Finalize\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The latest confirmation of a step: who and when, and `stale` when what they confirmed has changed since (the step is then open again). */
#[TypeScript]
final class GateConfirmation extends Data
{
    public function __construct(public string $by, public string $at, public bool $stale) {}
}

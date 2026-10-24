<?php

namespace Tey\Kit\Features\Core\Lists;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/** A list that could not load: a fixed message (never exception text) and an optional retry. */
#[TypeScript]
final class ListErrorData extends Data
{
    public function __construct(public string $message, public ?Action $retry = null) {}
}

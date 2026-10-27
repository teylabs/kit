<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A filter the IndexToolbar shows as a choice: its `filter[key]` name, its label and the values it accepts. A filter declared without a label is honoured but not shown. */
#[TypeScript]
final class TableFilter extends Data
{
    /** @param  list<array{value: string, label: string}>  $options */
    public function __construct(public string $key, public string $label, public array $options) {}
}

<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The table's active sort: a column key and a direction (`asc` or `desc`). */
#[TypeScript]
final class TableSort extends Data
{
    public function __construct(public string $column, public string $direction) {}
}

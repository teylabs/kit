<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A tab above a table: a link with its count, which the server computed. */
#[TypeScript]
final class TableTab extends Data
{
    public function __construct(public string $key, public string $label, public string $url, public int $count, public bool $current) {}
}

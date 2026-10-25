<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One column of a server-driven table, in display order. A column with no cell slot renders its row value as text. `sortBy` is what the table sends as `?sort=` when it differs from the column's `key` (the server's allowed sort is named differently); null means the key. */
#[TypeScript]
final class TableColumn extends Data
{
    public function __construct(public string $key, public string $label, public bool $sortable = false, public bool $hideable = false, public bool $hiddenByDefault = false, public ?string $align = null, public bool $srOnlyLabel = false, public ?string $sortBy = null) {}
}

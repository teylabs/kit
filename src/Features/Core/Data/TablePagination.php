<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Where the table is in its pages; `from`/`to` are null when there are no rows. */
#[TypeScript]
final class TablePagination extends Data
{
    public function __construct(public int $page, public int $perPage, public int $total, public ?int $from, public ?int $to, public int $lastPage) {}
}

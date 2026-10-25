<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the DataTable renders, built on the server: the rows (already filtered, sorted and paginated), the columns, the URL state, the tabs and the
 * empty state. A row is a plain map of column key to value, plus `id` and `actions` (a list of Action); the client decides nothing about permissions.
 */
#[TypeScript]
final class TableData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<TableColumn>  $columns
     * @param  array<string, string>  $filters
     * @param  list<TableTab>  $tabs
     * @param  'first-use'|'no-results'|null  $emptyState
     */
    public function __construct(public array $rows, public array $columns, public ?TableSort $sort, public ?string $search, public array $filters, public array $tabs, public TablePagination $pagination, public ?string $emptyState) {}
}

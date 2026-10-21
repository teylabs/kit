<?php

namespace Tey\Kit\Features\Core\Tables;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Tey\Kit\Features\Core\Data\TableColumn;
use Tey\Kit\Features\Core\Data\TableData;
use Tey\Kit\Features\Core\Data\TablePagination;
use Tey\Kit\Features\Core\Data\TableSort;

/**
 * The server side of a DataTable for a page that owns its own query: columns, a search, filters and a default sort, declared once. Only what is declared is
 * honoured; an unknown sort or filter is spatie/laravel-query-builder's 400, never silently ignored. State is `?sort=-name&filter[search]=x&filter[role]=y&page=2&per_page=25`
 * (the per-page is capped). Pages whose rows come from a package that already builds a TableData (the People index) do not need this.
 */
final class TableQuery
{
    public const int DEFAULT_PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /** @var list<TableColumn> */
    private array $columns = [];

    /** @var array<string, string> column key => the SQL column it sorts by */
    private array $sorts = [];

    private ?Closure $search = null;

    /** @var array<string, Closure> */
    private array $filters = [];

    private string $defaultSort = '';

    private bool $defaultDescending = false;

    /** @param  Builder<Model>  $query */
    private function __construct(private Builder $query, private Request $request) {}

    /** @param  Builder<Model>  $query */
    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /** @param  string|null  $sortBy  the SQL column the header sorts by; null is a column that cannot be sorted */
    public function column(string $key, string $label, ?string $sortBy = null, bool $hideable = false, bool $hiddenByDefault = false, ?string $align = null, bool $srOnlyLabel = false): self
    {
        $this->columns[] = new TableColumn($key, $label, $sortBy !== null, $hideable, $hiddenByDefault, $align, $srOnlyLabel);
        if ($sortBy !== null) {
            $this->sorts[$key] = $sortBy;
        }

        return $this;
    }

    /** @param  Closure(Builder<Model>, string): mixed  $search  narrows the query by `filter[search]` */
    public function search(Closure $search): self
    {
        $this->search = $search;

        return $this;
    }

    /** @param  Closure(Builder<Model>, string): mixed  $apply */
    public function filter(string $name, Closure $apply): self
    {
        $this->filters[$name] = $apply;

        return $this;
    }

    public function defaultSort(string $column, bool $descending = false): self
    {
        $this->defaultSort = $column;
        $this->defaultDescending = $descending;

        return $this;
    }

    /** @param  Closure(Model): array<string, mixed>  $row  the row's values by column key, plus `id` and `actions` */
    public function build(Closure $row): TableData
    {
        $allowed = [];
        if ($this->search !== null) {
            $allowed[] = AllowedFilter::callback('search', fn ($query, $value) => ($this->search)($query, (string) $value));
        }
        foreach ($this->filters as $name => $apply) {
            $allowed[] = AllowedFilter::callback($name, fn ($query, $value) => $apply($query, (string) $value));
        }
        $sorts = array_map(fn (string $key) => AllowedSort::field($key, $this->sorts[$key]), array_keys($this->sorts));
        $builder = QueryBuilder::for($this->query, $this->request)->allowedFilters(...$allowed)->allowedSorts(...$sorts);
        if ($this->defaultSort !== '') {
            $builder->defaultSort(($this->defaultDescending ? '-' : '').$this->defaultSort);
        }
        $perPage = min(max((int) $this->request->query('per_page', self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);
        $paginator = $builder->paginate($perPage, ['*'], 'page', max((int) $this->request->query('page', 1), 1));

        $applied = array_map('strval', array_filter(Arr::only((array) $this->request->query('filter', []), ['search', ...array_keys($this->filters)]), fn ($value) => is_string($value) && $value !== ''));
        $requested = (string) $this->request->query('sort', '');
        $column = ltrim($requested, '-');
        $sort = isset($this->sorts[$column]) ? new TableSort($column, str_starts_with($requested, '-') ? 'desc' : 'asc') : ($this->defaultSort !== '' ? new TableSort($this->defaultSort, $this->defaultDescending ? 'desc' : 'asc') : null);

        return new TableData(
            array_map($row, $paginator->items()),
            $this->columns,
            $sort,
            $applied['search'] ?? null,
            $applied,
            [],
            new TablePagination($paginator->currentPage(), $paginator->perPage(), $paginator->total(), $paginator->firstItem(), $paginator->lastItem(), $paginator->lastPage()),
            $paginator->total() === 0 ? ($applied === [] ? 'first-use' : 'no-results') : null,
        );
    }
}

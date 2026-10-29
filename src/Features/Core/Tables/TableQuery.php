<?php

namespace Tey\Kit\Features\Core\Tables;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\TableColumn;
use Tey\Kit\Features\Core\Data\TableData;
use Tey\Kit\Features\Core\Data\TableFilter;
use Tey\Kit\Features\Core\Data\TablePagination;
use Tey\Kit\Features\Core\Data\TableSort;

/**
 * The server side of a DataTable for a page that owns its own query: columns, a search, filters and a default sort, declared once. Only what is declared is
 * honoured; an unknown sort or filter is spatie/laravel-query-builder's 400, never silently ignored. State is `?sort=-name&filter[search]=x&filter[role]=y&page=2&per_page=25`
 * (the per-page is capped). A filter with a label and options is shown in the IndexToolbar, and a value outside its options is a 400 too; bulk actions make the
 * rows selectable (their handler reads the ids with TableSelection). Pages whose rows come from a package that already builds a TableData (the People index) do not need this.
 * A page past the last (a bookmarked page, or rows deleted since) redirects to the last page, keeping the session's flashed data (a toast) for it.
 *
 * @template TModel of Model
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

    /** @var list<TableFilter> */
    private array $choices = [];

    /** @var list<\Tey\Kit\Features\Core\Data\Action> */
    private array $bulkActions = [];

    private string $defaultSort = '';

    private bool $defaultDescending = false;

    /** @param  Builder<TModel>  $query */
    private function __construct(private Builder $query, private Request $request) {}

    /**
     * @template TFor of Model
     *
     * @param  Builder<TFor>  $query
     * @return self<TFor>
     */
    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /**
     * @param  string|null  $sortBy  the SQL column the header sorts by; null is a column that cannot be sorted
     *
     * @return $this
     */
    public function column(string $key, string $label, ?string $sortBy = null, bool $hideable = false, bool $hiddenByDefault = false, ?string $align = null, bool $srOnlyLabel = false): self
    {
        $this->columns[] = new TableColumn($key, $label, $sortBy !== null, $hideable, $hiddenByDefault, $align, $srOnlyLabel);
        if ($sortBy !== null) {
            $this->sorts[$key] = $sortBy;
        }

        return $this;
    }

    /**
     * @param  Closure(Builder<TModel>, string): mixed  $search  narrows the query by `filter[search]`
     *
     * @return $this
     */
    public function search(Closure $search): self
    {
        $this->search = $search;

        return $this;
    }

    /**
     * @param  Closure(Builder<TModel>, string): mixed  $apply
     * @param  array<string, string>  $options  value => label; with a label the toolbar shows the filter as a choice of these
     *
     * @return $this
     */
    public function filter(string $name, Closure $apply, ?string $label = null, array $options = []): self
    {
        if ($options === []) {
            $this->filters[$name] = $apply;
        } else {
            $this->filters[$name] = function ($query, string $value) use ($name, $apply, $options) {
                if (! array_key_exists($value, $options)) {
                    throw InvalidFilterValue::make($name, $value);
                }

                return $apply($query, $value);
            };
        }
        if ($label !== null) {
            $this->choices[] = new TableFilter($name, $label, array_map(fn ($value, $text) => ['value' => (string) $value, 'label' => $text], array_keys($options), $options));
        }

        return $this;
    }

    /**
     * What may be done to the selected rows; the action's URL receives `ids[]` (read them with TableSelection). Only declare an action the viewer may take.
     *
     * @return $this
     */
    public function bulk(Action $action): self
    {
        $this->bulkActions[] = $action;

        return $this;
    }

    /**
     * `$column` is a column KEY, as passed to column(): the sort runs on the SQL column it was declared with (a key `joinedAt` sorts by `created_at`).
     *
     * @return $this
     */
    public function defaultSort(string $column, bool $descending = false): self
    {
        $this->defaultSort = $column;
        $this->defaultDescending = $descending;

        return $this;
    }

    /** @param  Closure(TModel): array<string, mixed>  $row  the row's values by column key, plus `id` and `actions` */
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
            // Spatie reads a string default sort as its own column name; the key's declared SQL column is what the sort runs on.
            $builder->defaultSort(AllowedSort::field($this->defaultSort, $this->sorts[$this->defaultSort] ?? $this->defaultSort)->defaultDirection($this->defaultDescending ? SortDirection::Descending : SortDirection::Ascending));
        }
        $perPage = min(max((int) $this->request->query('per_page', self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);
        $paginator = $builder->paginate($perPage, ['*'], 'page', max((int) $this->request->query('page', 1), 1));
        // lastPage() is 1 for an empty result, so an empty page past 1 lands on page 1.
        if ($paginator->currentPage() > $paginator->lastPage()) {
            if ($this->request->hasSession()) {
                $this->request->session()->reflash();
            }
            throw new HttpResponseException(new RedirectResponse($this->request->fullUrlWithQuery(['page' => $paginator->lastPage()])));
        }

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
            $this->choices,
            $this->bulkActions,
        );
    }
}

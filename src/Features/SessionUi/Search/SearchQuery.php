<?php

namespace Tey\Kit\Features\SessionUi\Search;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Principal;

/** What a provider is asked: the trimmed term (1 to 100 characters), who asks, in which workspace, and how many results it may return. */
final readonly class SearchQuery
{
    public function __construct(public string $term, public Principal $principal, public int $workspace, public int $limit, public ?Request $request = null) {}

    /**
     * Narrows `$query` to rows where any of `$columns` contains the term, ignoring case on every database (`ilike` on PostgreSQL, where `like` is
     * case-sensitive). Use this rather than a hand-written `like`.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @param  list<string>  $columns
     * @return TBuilder
     */
    public function matches(Builder $query, array $columns): Builder
    {
        return $query->where(function ($where) use ($columns): void {
            foreach ($columns as $column) {
                $where->orWhereLike($column, $this->like());
            }
        });
    }

    /** The term ready for a `like` pattern: wildcards escaped, wrapped in `%`. Prefer `matches()`, which also ignores case on every database. */
    public function like(): string
    {
        return '%'.addcslashes($this->term, '%_\\').'%';
    }
}

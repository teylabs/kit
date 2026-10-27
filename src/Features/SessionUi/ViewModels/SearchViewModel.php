<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\SessionUi\Search\SearchResult;

/** Props for pages/Workspace/Search/Page.vue: the query, each provider's group of results, and whether any provider could not answer. */
#[TypeScript]
final class SearchViewModel extends PageViewModel
{
    /** @param  list<array{key: string, label: string, results: list<SearchResult>}>  $groups */
    public function __construct(private string $title, private string $query, private string $url, private array $groups, private bool $partial) {}

    public function title(): string
    {
        return $this->title;
    }

    public function query(): string
    {
        return $this->query;
    }

    /** Where the search form sends `q`. */
    public function url(): string
    {
        return $this->url;
    }

    /** @return list<array{key: string, label: string, results: list<SearchResult>}> */
    public function groups(): array
    {
        return $this->groups;
    }

    /** True when a provider failed: the results shown may be incomplete. */
    public function partial(): bool
    {
        return $this->partial;
    }
}

<?php

namespace Tey\Kit\Features\SessionUi\Search;

/**
 * One source of global search results, registered with `Registry::searchProvider()`. It is asked only inside the workspace's tenant context and only for a
 * viewer who holds its ability there, so it answers for `$query->workspace` and never reads another; it still scopes rows to what that viewer may see.
 * It returns at most `$query->limit` results, best first. It never throws on purpose: a provider that does is skipped, reported, and search still answers.
 */
interface SearchProvider
{
    /** @return list<SearchResult> */
    public function search(SearchQuery $query): array;
}

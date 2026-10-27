<?php

namespace Tey\Kit\Features\SessionUi\Search;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Principal;

/** What a provider is asked: the trimmed term (1 to 100 characters), who asks, in which workspace, and how many results it may return. */
final readonly class SearchQuery
{
    public function __construct(public string $term, public Principal $principal, public int $workspace, public int $limit, public ?Request $request = null) {}

    /** The term ready for a `like` pattern: wildcards escaped, wrapped in `%`. */
    public function like(): string
    {
        return '%'.addcslashes($this->term, '%_\\').'%';
    }
}

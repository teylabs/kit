<?php

namespace Tey\Kit\Features\SessionUi\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One search hit as every surface shows it: the palette option, the results page row and the MCP tool's result. `id` is unique within its provider;
 * `url` is where choosing it goes (null for a hit with no page of its own); `description` and `meta` are short plain text, never markup.
 */
#[TypeScript]
final class SearchResult extends Data
{
    public function __construct(public string $id, public string $label, public ?string $url = null, public ?string $description = null, public ?string $meta = null) {}
}

<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A CollectionSection's Sort by menu: the orders it offers (key and label, in menu order), the one shown, and where a choice goes: `url` with `method` (`get` for a
 * page that reads `?sort=`, `put` to save it for the viewer), sending `{ sort }`, and `reload`, the page props a choice reloads (empty: the whole page).
 * `token`, when the server sets one, goes back with the choice as `X-Kit-Sort: <token>&sort=<order>` (and so with the reload its redirect leads to), so
 * that reload can tell what was chosen from the request alone (a dashboard widget's, docs/dashboard.md). The same shape as a dashboard widget's `sortBy`
 * (WidgetSortData).
 */
#[TypeScript]
final class CollectionSortData extends Data
{
    /**
     * @param  list<array{key: string, label: string}>  $options
     * @param  list<string>  $reload
     */
    public function __construct(public array $options, public string $current, public string $url, public string $method = 'get', public array $reload = [], public string $label = 'Sort by', #[Optional] public ?string $token = null) {}
}

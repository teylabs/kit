<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A widget's Sort by control: the orders it offers, the viewer's current one, where a change is saved and which page props a change reloads (the widget's own
 * lists, else `widgets`). Built by `Widget::sortBy()` from the widget's declared orders; the client never names an order the widget did not declare.
 */
#[TypeScript]
final class WidgetSortData extends Data
{
    /**
     * @param  list<array{key: string, label: string}>  $options
     * @param  list<string>  $reload
     */
    public function __construct(public string $label, public array $options, public string $current, public string $url, public string $method, public array $reload) {}
}

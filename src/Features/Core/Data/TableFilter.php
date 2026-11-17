<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A filter the IndexToolbar shows as a choice: its `filter[key]` name, its label and the values it accepts. A filter declared without a label is honoured but not shown.
 * FilterPopover reads the same declaration: `kind` is `select` (one value, the default), `multi` (comma-joined values, ticked from options grouped by each option's
 * `group`; empty is "All …") or `search` (a text box that narrows the options of the multi filter it `narrows`, in the browser; it is never sent). `default` is the
 * value that counts as unfiltered (null: none).
 */
#[TypeScript]
final class TableFilter extends Data
{
    /**
     * @param  list<array{value: string, label: string, group?: string}>  $options
     * @param  'select'|'multi'|'search'  $kind
     */
    public function __construct(public string $key, public string $label, public array $options, public string $kind = 'select', public ?string $default = null, public ?string $narrows = null) {}
}

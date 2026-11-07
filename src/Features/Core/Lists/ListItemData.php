<?php

namespace Tey\Kit\Features\Core\Lists;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One row of a ScrollList. Richer rows extend this with typed fields and render through the list's `row` slot. */
#[TypeScript]
class ListItemData extends Data
{
    /**
     * @param  list<array{icon: string|null, label: string, url: string|null}>  $meta
     * @param  list<array{label: string, variant: string}>  $badges
     * @param  list<\Tey\Kit\Features\Core\Data\Action>  $actions
     * @param  array{key: string, label: string}|null  $group
     */
    public function __construct(public string $id, public string $title, public ?string $url = null, public ?string $description = null, public array $meta = [], public array $badges = [], public ?string $variant = null, public ?array $group = null, public array $actions = []) {}
}

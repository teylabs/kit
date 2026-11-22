<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A group of navigation items, with an optional label. A `nested` group is a nested module's pages, drawn as a second tab row under the first. */
#[TypeScript]
final class NavGroup extends Data
{
    /** @param  list<NavItem>  $items */
    public function __construct(public ?string $label, public array $items, public bool $nested = false) {}
}

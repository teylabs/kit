<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A group of navigation items, with an optional label. A `nested` group is a nested module's pages, drawn as a row of pills under the
 * tabs. `sidebar` groups are a sidebar module's (Registry::modulePage()'s `group`), drawn by <Page> as a grouped sidebar instead of tabs. `variant`
 * `secondary` is a top-level module that draws its own row as pills (Registry::moduleVariant()); null is underline tabs. */
#[TypeScript]
final class NavGroup extends Data
{
    /** @param  list<NavItem>  $items */
    public function __construct(public ?string $label, public array $items, public bool $nested = false, public bool $sidebar = false, public ?string $variant = null) {}
}

<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The way up one level, which a nested page asks the shell to draw as a strip under the header: an arrow and the `label` (a destination, such as "Back to Alpha"), going to `url`. With `trail`
 * (a breakout declared `with-trail`) the page keeps its breadcrumb trail as well; without it the strip replaces the trail. */
#[TypeScript]
final class NavigationHeader extends Data
{
    public function __construct(public string $label, public string $url, public bool $trail = false) {}
}

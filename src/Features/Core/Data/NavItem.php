<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One navigation item. `current` is decided on the server and covers descendants; the client never matches URLs itself. */
#[TypeScript]
final class NavItem extends Data
{
    public function __construct(public string $label, public string $url, public bool $current, public ?string $icon = null, public ?string $badge = null) {}
}

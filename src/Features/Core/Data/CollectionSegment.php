<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One segment tab above a CollectionSection's items ("All", "New", "In progress"): a link to the same page showing that segment, its optional count, and whether it is the one shown. */
#[TypeScript]
final class CollectionSegment extends Data
{
    public function __construct(public string $key, public string $label, public string $url, public bool $current = false, public ?int $count = null) {}
}

<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One step of the trail: root first, the last one current (its URL is its own). */
#[TypeScript]
final class Breadcrumb extends Data
{
    public function __construct(public string $label, public ?string $url, public bool $current) {}
}

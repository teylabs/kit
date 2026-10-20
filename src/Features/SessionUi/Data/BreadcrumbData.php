<?php

namespace Tey\Kit\Features\SessionUi\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One crumb of the trail. A null url is a page the user can't open, shown as plain text. */
#[TypeScript]
final class BreadcrumbData extends Data
{
    public function __construct(public string $label, public ?string $url, public bool $current) {}
}

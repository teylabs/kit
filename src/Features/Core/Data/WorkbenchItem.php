<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One item in a Workbench's navigator: its id (a string, so any key works), the label it is listed by, its open-ended `type` (a block or record type: which
 * inspector it gets) and an optional one-line `description` the filter also searches.
 */
#[TypeScript]
final class WorkbenchItem extends Data
{
    public function __construct(public string $id, public string $label, public ?string $type = null, public ?string $description = null) {}
}

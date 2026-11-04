<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A checklist row's action. With a `url` it is a link; without one, the component emits `action` with the item and this `name`, and the page decides what it does. */
#[TypeScript]
final class ChecklistAction extends Data
{
    public function __construct(public string $label, public ?string $url = null, public ?string $name = null) {}
}

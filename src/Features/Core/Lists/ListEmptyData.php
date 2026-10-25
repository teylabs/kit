<?php

namespace Tey\Kit\Features\Core\Lists;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/** What a list says when it has no rows: `first-use` (nothing exists yet) or `no-results` (the list changed under the viewer). */
#[TypeScript]
final class ListEmptyData extends Data
{
    public function __construct(public string $variant, public string $title, public string $description, public ?Action $action = null) {}
}

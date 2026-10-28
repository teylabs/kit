<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An inspector the page may register for a type (`@document` for nothing selected, `@fallback` for a selection nothing covers): the server says which exist and how
 * they are labelled; the page maps each to its component with `useWorkbench(key).defineInspector()`. `types` lists the types a common inspector covers.
 */
#[TypeScript]
final class WorkbenchInspector extends Data
{
    /** @param list<string> $types */
    public function __construct(public string $type, public string $label, public array $types = []) {}
}

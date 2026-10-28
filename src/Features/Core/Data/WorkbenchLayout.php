<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A person's remembered Workbench layout (pane sizes, collapsed panes, zoom, the phone tab) and where to save it. `state` is null before the first save; `version`
 * goes with each write, and the endpoint refuses a stale one. `preference` is null where the kit's endpoint is not available (the page then keeps the layout in
 * the browser only).
 */
#[TypeScript]
final class WorkbenchLayout extends Data
{
    /**
     * @param  ?array{layout: array<string, mixed>|null, version: int}  $state
     * @param  ?array{url: string, method: string}  $preference
     */
    public function __construct(public ?array $state, public ?array $preference) {}
}

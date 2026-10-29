<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One item in a Workbench's navigator: its id (a string, so any key works), the label it is listed by, its open-ended `type` (a block or record type: which
 * inspector it gets), an optional one-line `description` the filter also searches and its row menu's `actions` (kit Action descriptors, docs/workbench.md › Item
 * menus): an empty list means no menu. The host decides per item which actions to send; the MCP read tool leaves them out (UI affordances, not content).
 */
#[TypeScript]
final class WorkbenchItem extends Data
{
    /** @param  list<\Tey\Kit\Features\Core\Data\Action>  $actions */
    public function __construct(public string $id, public string $label, public ?string $type = null, public ?string $description = null, public array $actions = []) {}
}

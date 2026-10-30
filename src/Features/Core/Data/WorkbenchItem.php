<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One item in a Workbench's navigator: its id (a string, so any key works), the label it is listed by, its open-ended `type` (a block or record type: which
 * inspector it gets), an optional one-line `description` the filter also searches, its row menu's `actions` (kit Action descriptors, docs/workbench.md › Item
 * menus): an empty list means no menu, and an optional `thumbnail` (docs/workbench.md › Thumbnails and filmstrip). The host decides per item which actions to
 * send; the MCP read tool leaves out both actions and thumbnail (UI affordances, not content).
 */
#[TypeScript]
final class WorkbenchItem extends Data
{
    /**
     * @param  list<\Tey\Kit\Features\Core\Data\Action>  $actions
     * @param  ?\Tey\Kit\Features\Core\Data\WorkbenchThumbnail  $thumbnail
     */
    public function __construct(public string $id, public string $label, public ?string $type = null, public ?string $description = null, public array $actions = [], public ?WorkbenchThumbnail $thumbnail = null) {}
}

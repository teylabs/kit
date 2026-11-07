<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Something the viewer may do, decided on the server. A page never reads a permission or a role: it gets an action, `null` when it is not allowed (the control is
 * absent), or `disabled` with a `reason` when it is shown but cannot be used. `confirm` asks first (the page opens a ConfirmDialog from it); with `typed` it asks for that text to be typed, sent back as `confirmation`, which the action's operation re-checks with TypedConfirmation::require(). For ActionBar: `intent` (`dialog`
 * opens the route modal, `document` a new tab, `edit` an action that changes the record, hidden inside a locked LockedForm), `group` and `menu` declare its layout, `icon` names a known icon,
 * and `newTab` labels a link that opens a new tab. For a page header (Page, FloatingToolbar, the Workbench's actions row), `priority` (`primary`, `secondary`,
 * `overflow` or null) places it: one filled primary, up to two secondaries, the rest behind More (headerSplit). In a menu, `checked` (true or false) makes the row
 * a checkbox item (role `menuitemcheckbox` with `aria-checked`); choosing it runs the action as any other, and the next response carries the flipped value.
 * `ariaLabel` is the accessible name when the visible label, a verb, needs its record ("Remove Billing"); RowActions, ActionBar and BulkBar put it on the control.
 * Without it RowActions names a row action "<Label> <row>". Keep `label` a short verb and put the record in `confirm`'s description; its title stays short
 * ("Remove permission?"). `description` is a short line under the label in a RowActions menu (muted, truncated) and the item's accessible description, for
 * rows that share a label: several "Remove" rows each name their grant.
 */
#[TypeScript]
final class Action extends Data
{
    /** @param  ?array{title?: string|null, description: string, label: string, typed?: string}  $confirm */
    public function __construct(public string $label, public string $url, public string $method = 'post', public bool $disabled = false, public ?string $reason = null, public ?array $confirm = null, public ?string $key = null, public bool $destructive = false, public ?string $intent = null, public ?string $group = null, public bool $menu = false, public ?string $icon = null, public ?bool $newTab = null, #[LiteralTypeScriptType("'primary' | 'secondary' | 'overflow' | null")] public ?string $priority = null, public ?bool $checked = null, public ?string $ariaLabel = null, public ?string $description = null) {}

    public static function disabled(string $label, string $url, string $method, string $reason): self
    {
        return new self($label, $url, $method, true, $reason);
    }
}

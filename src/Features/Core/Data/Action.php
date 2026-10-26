<?php

namespace Tey\Kit\Features\Core\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Something the viewer may do, decided on the server. A page never reads a permission or a role: it gets an action, `null` when it is not allowed (the control is
 * absent), or `disabled` with a `reason` when it is shown but cannot be used. `confirm` asks first (the page opens a ConfirmDialog from it). For ActionBar: `intent` (`dialog`
 * opens the route modal, `document` a new tab), `group` and `menu` declare its layout, `icon` names a known icon, and `newTab` labels a link that opens a new tab.
 */
#[TypeScript]
final class Action extends Data
{
    /** @param  ?array{title: string, description: string, label: string}  $confirm */
    public function __construct(public string $label, public string $url, public string $method = 'post', public bool $disabled = false, public ?string $reason = null, public ?array $confirm = null, public ?string $key = null, public bool $destructive = false, public ?string $intent = null, public ?string $group = null, public bool $menu = false, public ?string $icon = null, public ?bool $newTab = null) {}

    public static function disabled(string $label, string $url, string $method, string $reason): self
    {
        return new self($label, $url, $method, true, $reason);
    }
}

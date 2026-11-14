<?php

namespace Tey\Kit\Features\Settings\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SettingsView extends Data
{
    /**
     * @param  list<array{label: string, href: string}>  $navigation
     * @param  'auto'|'12h'|'24h'|null  $clock  the workspace's time format (`auto` when it has none), or null when the app keeps none (Registry::workspaceClockUsing)
     */
    public function __construct(public int $workspace_id, public string $display_name, public string $timezone, public array $navigation, public int $version, public ?string $clock = null) {}
}

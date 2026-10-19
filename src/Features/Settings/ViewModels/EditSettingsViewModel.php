<?php

namespace Tey\Kit\Features\Settings\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Settings\Data\SettingsView;

/** Props for pages/Workspace/Settings/Edit/Page.vue. */
#[TypeScript]
final class EditSettingsViewModel extends Data
{
    public function __construct(public string $title, public SettingsView $settings, public bool $can_write, public string $update_url, public string $intent_key) {}
}

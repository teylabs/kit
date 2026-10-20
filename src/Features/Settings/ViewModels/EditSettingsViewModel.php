<?php

namespace Tey\Kit\Features\Settings\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Settings\Data\SettingsView;

/** Props for pages/Workspace/Settings/General/Page.vue. */
#[TypeScript]
final class EditSettingsViewModel extends PageViewModel
{
    public function __construct(private string $title, private SettingsView $settings, private bool $can_write, private string $update_url, private string $intent_key) {}

    public function title(): string
    {
        return $this->title;
    }

    public function settings(): SettingsView
    {
        return $this->settings;
    }

    public function can_write(): bool
    {
        return $this->can_write;
    }

    public function update_url(): string
    {
        return $this->update_url;
    }

    public function intent_key(): string
    {
        return $this->intent_key;
    }

    protected function section(): ?string
    {
        return 'workspace';
    }

}

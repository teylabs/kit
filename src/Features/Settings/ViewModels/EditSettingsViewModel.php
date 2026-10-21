<?php

namespace Tey\Kit\Features\Settings\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Settings\Data\SettingsView;

/** Props for pages/Workspace/Settings/General/Page.vue. */
#[TypeScript]
final class EditSettingsViewModel extends PageViewModel
{
    public function __construct(private string $title, private SettingsView $settings, private ?Action $save, private string $intent_key) {}

    public function title(): string
    {
        return $this->title;
    }

    public function settings(): SettingsView
    {
        return $this->settings;
    }

    /** Save: absent when the viewer's role can only read the settings. */
    public function save(): ?Action
    {
        return $this->save;
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

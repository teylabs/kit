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
    public function __construct(private string $title, private SettingsView $settings, private Action $save, private string $intent_key, private ?Action $transfer = null, private array $heirs = [], private ?Action $leave = null) {}

    public function title(): string
    {
        return $this->title;
    }

    public function settings(): SettingsView
    {
        return $this->settings;
    }

    /** Save: disabled, with the reason, when the viewer may read but not change the settings. */
    public function save(): Action
    {
        return $this->save;
    }

    public function intent_key(): string
    {
        return $this->intent_key;
    }

    /** Transfer ownership: only the owner has it (null for everyone else). */
    public function transfer(): ?Action
    {
        return $this->transfer;
    }

    /** @return list<array{value: string, label: string}> the members the owner may hand the workspace to (empty for everyone else) */
    public function heirs(): array
    {
        return $this->heirs;
    }

    /** Leave workspace: disabled, with the reason, for the owner; null for someone who is not a member. */
    public function leave(): ?Action
    {
        return $this->leave;
    }

    protected function section(): ?string
    {
        return 'workspace';
    }

}

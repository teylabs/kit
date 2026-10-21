<?php

namespace Tey\Kit\Features\Settings\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Settings\Data\SettingsView;

/** Props for pages/Workspace/Settings/General/Page.vue. */
#[TypeScript]
final class EditSettingsViewModel extends PageViewModel
{
    public function __construct(private string $title, private SettingsView $settings, private bool $can_write, private string $update_url, private string $intent_key, private bool $is_owner = false, private array $heirs = [], private string $transfer_url = '', private ?string $leave_url = null) {}

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

    public function is_owner(): bool
    {
        return $this->is_owner;
    }

    /** @return list<array{value: string, label: string}> the members the owner may hand the workspace to (empty for everyone else) */
    public function heirs(): array
    {
        return $this->heirs;
    }

    public function transfer_url(): string
    {
        return $this->transfer_url;
    }

    public function leave_url(): ?string
    {
        return $this->leave_url;
    }

    protected function section(): ?string
    {
        return 'workspace';
    }

}

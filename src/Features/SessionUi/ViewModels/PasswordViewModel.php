<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Account/Password/Page.vue: change your password (Fortify's update, on `update_url`). */
#[TypeScript]
final class PasswordViewModel extends PageViewModel
{
    public function __construct(private string $title, private string $update_url, private ?string $notice) {}

    public function title(): string
    {
        return $this->title;
    }

    public function update_url(): string
    {
        return $this->update_url;
    }

    public function notice(): ?string
    {
        return $this->notice;
    }

    protected function section(): ?string
    {
        return 'personal';
    }

}

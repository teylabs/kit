<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Account/Delete/Page.vue: delete your account with your password, refused while you own a workspace (`owned` names them). */
#[TypeScript]
final class DeleteAccountViewModel extends PageViewModel
{
    /** @param  list<string>  $owned */
    public function __construct(private string $title, private array $owned, private Action $submit, private bool $confirms_password = true) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<string> */
    public function owned(): array
    {
        return $this->owned;
    }

    public function submit(): Action
    {
        return $this->submit;
    }

    /** Whether the form asks for the current password (`kit.lifecycle.confirm_password`). */
    public function confirms_password(): bool
    {
        return $this->confirms_password;
    }

    protected function section(): ?string
    {
        return 'personal';
    }
}

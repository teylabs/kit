<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Settings/Delete/Page.vue: the owner deletes the workspace, typing its name to confirm. */
#[TypeScript]
final class DeleteWorkspaceViewModel extends PageViewModel
{
    public function __construct(private string $title, private string $workspace_name, private int $members, private Action $submit, private bool $confirms_password = true) {}

    public function title(): string
    {
        return $this->title;
    }

    public function workspace_name(): string
    {
        return $this->workspace_name;
    }

    public function members(): int
    {
        return $this->members;
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
        return 'workspace';
    }
}

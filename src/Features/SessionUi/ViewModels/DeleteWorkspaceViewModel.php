<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/**
 * Props for pages/Workspace/Settings/Delete/Page.vue: the owner deletes the workspace, typing its name to confirm.
 *
 * @purpose Workspace owner: delete the workspace, rarely.
 */
#[TypeScript]
final class DeleteWorkspaceViewModel extends PageViewModel
{
    public function __construct(private string $title, private string $workspace_name, private int $members, private Action $submit, private bool $confirms_password = true, private array $removes = ['settings', 'invitations']) {}

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

    /**
     * What else goes with the workspace, as the warning lists it: its settings and invitations, and its notes only where the notes feature is installed.
     *
     * @return list<string>
     */
    public function removes(): array
    {
        return array_values($this->removes);
    }

    protected function section(): ?string
    {
        return 'workspace';
    }
}

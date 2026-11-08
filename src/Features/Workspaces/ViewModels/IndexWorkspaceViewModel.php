<?php

namespace Tey\Kit\Features\Workspaces\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/**
 * Props for pages/Workspace/Index/Page.vue.
 *
 * @purpose Signed-in person: pick the workspace to open, or start one.
 */
#[TypeScript]
final class IndexWorkspaceViewModel extends PageViewModel
{
    /**
     * @param  list<array{id: int, name: string, initials: string, role: ?string, members: int, current: bool, leave: ?\Tey\Kit\Features\Core\Data\Action}>  $workspaces  only the person's own active memberships (the link into each is the shell's switcher choice, by id); `leave` absent for a workspace they own (transfer ownership first)
     * @param  list<array{workspace: string, inviter: ?string, message: ?string, expires: ?string}>  $invitations  open invitations to the person's verified email
     * @param  ?\Tey\Kit\Features\Core\Data\Action  $create  New workspace, present only when provisioning allows it (never with `invite_only`)
     */
    public function __construct(private string $title, private array $workspaces, private array $invitations = [], private ?Action $create = null) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<array{id: int, name: string, initials: string, role: ?string, members: int, current: bool, leave: ?\Tey\Kit\Features\Core\Data\Action}> */
    public function workspaces(): array
    {
        return $this->workspaces;
    }

    /** @return list<array{workspace: string, inviter: ?string, message: ?string, expires: ?string}> */
    public function invitations(): array
    {
        return $this->invitations;
    }

    public function create(): ?Action
    {
        return $this->create;
    }
}

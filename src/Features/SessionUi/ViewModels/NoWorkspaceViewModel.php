<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Provision/NoWorkspace/Page.vue: a signed-in person with no workspace (a new account, or after leaving or being removed): never a 403, always a way forward. */
#[TypeScript]
final class NoWorkspaceViewModel extends PageViewModel
{
    /**
     * @param  list<array{workspace: string, inviter: ?string, message: ?string, expires: ?string}>  $invitations  invitations sent to their email that are still open
     * @param  ?\Tey\Kit\Features\Core\Data\Action  $create  a way to start a workspace, present only when provisioning allows it (never with `invite_only`)
     */
    public function __construct(private string $title, private string $email, private array $invitations, private string $explanation, private ?Action $create, private Action $account) {}

    public function title(): string
    {
        return $this->title;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function invitations(): array
    {
        return $this->invitations;
    }

    public function explanation(): string
    {
        return $this->explanation;
    }

    public function create(): ?Action
    {
        return $this->create;
    }

    public function account(): Action
    {
        return $this->account;
    }
}

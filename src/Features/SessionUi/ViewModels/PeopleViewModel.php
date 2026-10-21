<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Settings/People/Page.vue: a workspace's members and pending invitations, and what the viewer may do with them. */
#[TypeScript]
final class PeopleViewModel extends PageViewModel
{
    /**
     * @param  list<array{id: int, name: string, email: string, initials: string, avatar: ?array{url: string, url_2x: string}, role: string, role_label: string, joined_at: ?string, is_you: bool, change_role: ?\Tey\Kit\Features\Core\Data\Action, remove: ?\Tey\Kit\Features\Core\Data\Action}>  $members
     * @param  list<array{id: string, email: string, role: string, role_label: string, expires_at: string, expired: bool, invited_by: ?string, resend: ?\Tey\Kit\Features\Core\Data\Action, revoke: ?\Tey\Kit\Features\Core\Data\Action}>  $invitations
     * @param  list<array{value: string, label: string}>  $roles  the roles the viewer may grant
     */
    public function __construct(private string $title, private array $members, private array $invitations, private array $roles, private \Tey\Kit\Features\Core\Data\Action $invite, private string $search, private string $sort, private ?string $invite_link = null) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<array{id: int, name: string, email: string, initials: string, avatar: ?array{url: string, url_2x: string}, role: string, role_label: string, joined_at: ?string, is_you: bool, change_role: ?\Tey\Kit\Features\Core\Data\Action, remove: ?\Tey\Kit\Features\Core\Data\Action}> */
    public function members(): array
    {
        return $this->members;
    }

    /** @return list<array{id: string, email: string, role: string, role_label: string, expires_at: string, expired: bool, invited_by: ?string, resend: ?\Tey\Kit\Features\Core\Data\Action, revoke: ?\Tey\Kit\Features\Core\Data\Action}> */
    public function invitations(): array
    {
        return $this->invitations;
    }

    /** @return list<array{value: string, label: string}> */
    public function roles(): array
    {
        return $this->roles;
    }

    /** Send an invitation: disabled, with the reason, when the viewer may grant no role. */
    public function invite(): \Tey\Kit\Features\Core\Data\Action
    {
        return $this->invite;
    }

    public function search(): string
    {
        return $this->search;
    }

    public function sort(): string
    {
        return $this->sort;
    }

    public function invite_link(): ?string
    {
        return $this->invite_link;
    }

    protected function section(): ?string
    {
        return 'workspace';
    }

}

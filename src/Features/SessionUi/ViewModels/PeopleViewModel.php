<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Settings/People/Page.vue: a workspace's members and pending invitations, and what the viewer may do with them. */
#[TypeScript]
final class PeopleViewModel extends PageViewModel
{
    /**
     * @param  list<array{id: int, name: string, email: string, initials: string, avatar: ?array{url: string, url_2x: string}, role: string, role_label: string, joined_at: ?string, is_you: bool, manageable: bool, update_url: string, destroy_url: string}>  $members
     * @param  list<array{id: string, email: string, role: string, role_label: string, expires_at: string, expired: bool, invited_by: ?string, resend_url: string, revoke_url: string}>  $invitations
     * @param  list<array{value: string, label: string}>  $roles  the roles the viewer may grant
     */
    public function __construct(private string $title, private array $members, private array $invitations, private array $roles, private bool $can_manage, private string $invite_url, private string $search, private string $sort, private ?string $invite_link = null) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<array{id: int, name: string, email: string, initials: string, avatar: ?array{url: string, url_2x: string}, role: string, role_label: string, joined_at: ?string, is_you: bool, manageable: bool, update_url: string, destroy_url: string}> */
    public function members(): array
    {
        return $this->members;
    }

    /** @return list<array{id: string, email: string, role: string, role_label: string, expires_at: string, expired: bool, invited_by: ?string, resend_url: string, revoke_url: string}> */
    public function invitations(): array
    {
        return $this->invitations;
    }

    /** @return list<array{value: string, label: string}> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function can_manage(): bool
    {
        return $this->can_manage;
    }

    public function invite_url(): string
    {
        return $this->invite_url;
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

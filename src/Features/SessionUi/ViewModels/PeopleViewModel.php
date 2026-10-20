<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Workspace/People/Index/Page.vue: a workspace's members and pending invitations, and what the viewer may do with them. */
#[TypeScript]
final class PeopleViewModel extends Data
{
    /**
     * @param  list<array{id: int, name: string, email: string, initials: string, avatar: ?array{url: string, url_2x: string}, role: string, role_label: string, joined_at: ?string, is_you: bool, manageable: bool, update_url: string, destroy_url: string}>  $members
     * @param  list<array{id: string, email: string, role: string, role_label: string, expires_at: string, expired: bool, invited_by: ?string, resend_url: string, revoke_url: string}>  $invitations
     * @param  list<array{value: string, label: string}>  $roles  the roles the viewer may grant
     */
    public function __construct(public string $title, public array $members, public array $invitations, public array $roles, public bool $can_manage, public string $invite_url, public string $search, public string $sort) {}
}

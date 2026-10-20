<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Auth/Invitation/Show/Page.vue: what a workspace invitation link shows, whoever follows it. */
#[TypeScript]
final class InvitationViewModel extends Data
{
    /**
     * @param  string  $state  Concierge's state: ready, sign_in_required, register_required, wrong_account, unverified, already_member, expired, revoked, invalid, accepted...
     * @param  ?array{workspace: string, inviter: ?string, role: string, email: string}  $invitation  null when the link names no invitation
     */
    public function __construct(public string $title, public string $state, public string $message, public ?array $invitation, public ?string $signed_in_as, public string $accept_url, public string $register_url, public string $login_url, public ?string $workspace_url) {}
}

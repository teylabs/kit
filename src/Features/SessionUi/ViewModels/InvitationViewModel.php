<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Auth/Invitation/Show/Page.vue: what a workspace invitation link shows, whoever follows it. */
#[TypeScript]
final class InvitationViewModel extends PageViewModel
{
    /**
     * @param  string  $state  Concierge's state: ready, sign_in_required, register_required, wrong_account, unverified, already_member, expired, revoked, invalid, accepted...
     * @param  ?array{workspace: string, inviter: ?string, role: string, email: string}  $invitation  null when the link names no invitation
     */
    public function __construct(private string $title, private string $state, private string $message, private ?array $invitation, private ?string $signed_in_as, private string $accept_url, private string $register_url, private string $login_url, private ?string $workspace_url) {}

    public function title(): string
    {
        return $this->title;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return ?array{workspace: string, inviter: ?string, role: string, email: string} */
    public function invitation(): ?array
    {
        return $this->invitation;
    }

    public function signed_in_as(): ?string
    {
        return $this->signed_in_as;
    }

    public function accept_url(): string
    {
        return $this->accept_url;
    }

    public function register_url(): string
    {
        return $this->register_url;
    }

    public function login_url(): string
    {
        return $this->login_url;
    }

    public function workspace_url(): ?string
    {
        return $this->workspace_url;
    }

}

<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Account/Profile/Page.vue: who you are and your photo. */
#[TypeScript]
final class ProfileViewModel extends PageViewModel
{
    /** @param ?array{url: string, url_2x: string} $avatar */
    public function __construct(private string $title, private string $name, private string $initials, private ?array $avatar, private string $store_url, private int $max_kb, private string $email = '', private bool $email_verified = true, private string $profile_url = '/user/profile-information', private ?string $notice = null, private string $verify_url = '/email/verification-notification') {}

    public function title(): string
    {
        return $this->title;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function initials(): string
    {
        return $this->initials;
    }

    /** @return ?array{url: string, url_2x: string} */
    public function avatar(): ?array
    {
        return $this->avatar;
    }

    public function store_url(): string
    {
        return $this->store_url;
    }

    public function max_kb(): int
    {
        return $this->max_kb;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function email_verified(): bool
    {
        return $this->email_verified;
    }

    public function profile_url(): string
    {
        return $this->profile_url;
    }

    public function notice(): ?string
    {
        return $this->notice;
    }

    public function verify_url(): string
    {
        return $this->verify_url;
    }

    protected function section(): ?string
    {
        return 'personal';
    }

}

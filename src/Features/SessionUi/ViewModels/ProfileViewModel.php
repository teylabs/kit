<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/**
 * Props for pages/Workspace/Account/Profile/Page.vue: who you are, your photo, and your date and time: your time zone and your clock (`workspace`
 * follows the workspace's, else `12h` or `24h`), both saved to `clock_url`.
 *
 * @purpose Signed-in person: keep their name and photo current.
 */
#[TypeScript]
final class ProfileViewModel extends PageViewModel
{
    /** @param ?array{url: string, url_2x: string} $avatar */
    public function __construct(private string $title, private string $name, private string $initials, private ?array $avatar, private string $store_url, private int $max_kb, private string $email = '', private bool $email_verified = true, private string $profile_url = '/user/profile-information', private ?string $notice = null, private string $verify_url = '/email/verification-notification', private string $clock = 'workspace', private string $clock_url = '/me/profile/clock', private string $timezone = 'UTC') {}

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

    /** @return 'workspace'|'12h'|'24h' */
    public function clock(): string
    {
        return $this->clock;
    }

    public function clock_url(): string
    {
        return $this->clock_url;
    }

    /** Your saved time zone, else the browser's, else UTC. */
    public function timezone(): string
    {
        return $this->timezone;
    }

    protected function section(): ?string
    {
        return 'personal';
    }
}

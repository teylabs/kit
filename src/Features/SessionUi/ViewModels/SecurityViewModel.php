<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Account/Security/Page.vue: two-factor authentication, signed-in devices and the security log. */
#[TypeScript]
final class SecurityViewModel extends PageViewModel
{
    /**
     * @param  array{state: string, qr_svg: ?string, secret_key: ?string, codes_left: int, recovery_codes: list<string>}  $two_factor  state: unavailable, off, pending (waiting for a code) or on
     * @param  array{supported: bool, items: list<array{agent: string, ip: ?string, last_active: string, current: bool}>}  $sessions
     * @param  list<array{type: string, label: string, ip: ?string, agent: string, at: string}>  $events
     */
    public function __construct(private string $title, private array $two_factor, private array $sessions, private array $events, private string $two_factor_url, private string $recovery_codes_url, private string $sessions_url, private ?string $notice) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return array{state: string, qr_svg: ?string, secret_key: ?string, codes_left: int, recovery_codes: list<string>} */
    public function two_factor(): array
    {
        return $this->two_factor;
    }

    /** @return array{supported: bool, items: list<array{agent: string, ip: ?string, last_active: string, current: bool}>} */
    public function sessions(): array
    {
        return $this->sessions;
    }

    /** @return list<array{type: string, label: string, ip: ?string, agent: string, at: string}> */
    public function events(): array
    {
        return $this->events;
    }

    public function two_factor_url(): string
    {
        return $this->two_factor_url;
    }

    public function recovery_codes_url(): string
    {
        return $this->recovery_codes_url;
    }

    public function sessions_url(): string
    {
        return $this->sessions_url;
    }

    public function notice(): ?string
    {
        return $this->notice;
    }

    protected function section(): ?string
    {
        return 'personal';
    }
}

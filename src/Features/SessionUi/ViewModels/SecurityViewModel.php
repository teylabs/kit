<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\CollectionSectionData;
use Tey\Kit\Features\Core\Lists\ListEmptyData;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/**
 * Props for pages/Workspace/Account/Security/Page.vue: two-factor authentication, signed-in devices and the security log.
 *
 * @purpose Signed-in person: manage two-factor and signed-in devices; check the security log.
 */
#[TypeScript]
final class SecurityViewModel extends PageViewModel
{
    /**
     * @param  array{state: string, qr_svg: ?string, secret_key: ?string, codes_left: int, recovery_codes: list<string>}  $two_factor  state: unavailable, off, pending (waiting for a code) or on
     * @param  array{supported: bool, items: list<array{agent: string, ip: ?string, last_active: string, current: bool}>}  $sessions
     * @param  list<array{type: string, label: string, ip: ?string, agent: string, at: string}>  $events
     * @param  ?array{options_url: string, store_url: string, confirm_url: ?string, url: string, items: list<array{id: int, name: string, created: string, last_used: ?string}>}  $passkeys  null without Fortify's passkeys feature; `url` is the rename/remove base (`{url}/{id}`)
     */
    public function __construct(private string $title, private array $two_factor, private array $sessions, private array $events, private string $two_factor_url, private string $recovery_codes_url, private string $sessions_url, private ?string $notice, private ?array $passkeys = null) {}

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

    /** @return ?array{options_url: string, store_url: string, confirm_url: ?string, url: string, items: list<array{id: int, name: string, created: string, last_used: ?string}>} */
    public function passkeys(): ?array
    {
        return $this->passkeys;
    }

    public function notice(): ?string
    {
        return $this->notice;
    }

    /** The security log's section (a CollectionSection over `events`, on a card): its title, what it holds, and what it says before there is anything to show. */
    public function log(): CollectionSectionData
    {
        return new CollectionSectionData('Security log', 'Your recent sign-ins and security changes. Only you can see this.', $this->events === [] ? 'empty' : 'ready',
            new ListEmptyData('first-use', 'Nothing yet', 'Your sign-ins and security changes show up here.'));
    }

    protected function section(): ?string
    {
        return 'personal';
    }
}

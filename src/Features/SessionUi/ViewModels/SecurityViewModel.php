<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Workspace/Security/Edit/Page.vue: two-factor authentication, signed-in devices and the security log. */
#[TypeScript]
final class SecurityViewModel extends Data
{
    /**
     * @param  array{state: string, qr_svg: ?string, secret_key: ?string, codes_left: int, recovery_codes: list<string>}  $two_factor  state: unavailable, off, pending (waiting for a code) or on
     * @param  array{supported: bool, items: list<array{agent: string, ip: ?string, last_active: string, current: bool}>}  $sessions
     * @param  list<array{type: string, label: string, ip: ?string, agent: string, at: string}>  $events
     */
    public function __construct(public string $title, public array $two_factor, public array $sessions, public array $events, public string $two_factor_url, public string $recovery_codes_url, public string $sessions_url, public ?string $notice) {}
}

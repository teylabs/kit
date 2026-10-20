<?php

namespace Tey\Kit\Features\SessionUi\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The shell's server-built view: the product, a workspace switcher, the nav in its slots, the user and the environment. */
#[TypeScript]
final class ShellData extends Data
{
    /**
     * @param  array{name: string, url: string, logomark: ?string}  $product  the platform's identity: `APP_NAME`, its home and its logomark; never a workspace's name
     * @param  array{current: ?array{id: int, name: string}, choices: list<array{id: int, name: string, url: ?string, current: bool}>}  $switcher  the choices are every active workspace; a one-workspace switcher is for the shell to hide
     * @param  list<array{label: string, url: string, current: bool, group: string}>  $navigation  the current workspace's primary pages, with Settings as one link after them
     * @param  ?array{name: string, email: string, initials: string, avatar_url: ?string, avatar_url_2x: ?string, items: list<array{label: string, url: string, current: bool, group: string}>}  $user  the signed-in user and their menu pages; null for a guest
     * @param  array{home: string, switcher: string, sign_in: string, sign_out: ?string}  $links
     * @param  array{identity: string, credit: string, links: list<array{label: string, url: string}>}  $footer  the web footer's lines: the identity, the credit line, then Terms and Privacy; no ©
     */
    public function __construct(public array $product, public array $switcher, public array $navigation, public ?array $user, public array $links, public ?string $env, public array $footer) {}
}

<?php

namespace Tey\Kit\Features\SessionUi\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/** The shell's server-built view: the product, a workspace switcher, the nav in its slots, the user and the environment. */
#[TypeScript]
final class ShellData extends Data
{
    /**
     * @param  array{label: string, url: string, current: bool}  $account  the account entry of the workspace menu; `current` on an account page
     * @param  array{name: string, url: string, logomark: ?string, logomark_dark: ?string, wordmark: ?string, wordmark_dark: ?string, lockup: bool}  $product  the platform's identity: `APP_NAME`, its home and its logomark; never a workspace's name
     * @param  ?array{id: int, name: string, url: ?string, settings: ?Action, all: ?Action, leave: ?Action}  $workspace  the workspace the bar names: the current one, or the only one; `settings` is present only when the viewer can open a settings section; `all` (the chooser) only with two or more workspaces; `leave` only for a member who is not the owner
     * @param  ?array{current: ?array{id: int, name: string}, choices: list<array{id: int, name: string, url: ?string, current: bool}>}  $switcher  present only with two or more workspaces; the shell renders what is here and decides nothing
     * @param  list<array{label: string, url: string, current: bool, group: string}>  $navigation  the current workspace's primary pages, with Settings as one link after them
     * @param  ?array{name: string, email: string, initials: string, avatar_url: ?string, avatar_url_2x: ?string, items: list<array{label: string, url: string, current: bool, group: string}>}  $user  the signed-in user and their menu pages; null for a guest
     * @param  array{home: string, switcher: string, sign_in: string, sign_out: ?string}  $links
     * @param  array{identity: string, credit: string, links: list<array{label: string, url: string}>}  $footer  the web footer's lines: the identity, the credit line, then Terms and Privacy; no ©
     */
    public function __construct(public array $account, public array $product, public ?array $workspace, public ?array $switcher, public array $navigation, public ?array $user, public array $links, public array $footer, public ShellKind $kind = ShellKind::App) {}
}

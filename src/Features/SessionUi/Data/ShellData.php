<?php

namespace Tey\Kit\Features\SessionUi\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The shell's server-built view: the product, a workspace switcher, the nav in its slots, the user and the environment. */
#[TypeScript]
final class ShellData extends Data
{
    /**
     * @param  array{name: string, url: string}  $product  the app's own name and home
     * @param  array{current: ?array{id: int, name: string}, choices: list<array{id: int, name: string, url: ?string, current: bool}>}  $switcher  the choices are every active workspace; a one-workspace switcher is for the shell to hide
     * @param  list<array{label: string, url: string, current: bool, group: string}>  $navigation  the current workspace's primary pages
     * @param  ?array{label: string, current: bool, items: list<array{label: string, url: string, current: bool, group: string}>}  $settings  null when the workspace has no settings pages
     * @param  ?array{name: string, email: string, initials: string, items: list<array{label: string, url: string, current: bool, group: string}>}  $user  the signed-in user and their menu pages; null for a guest
     * @param  array{home: string, switcher: string, sign_in: string, sign_out: ?string}  $links
     * @param  list<BreadcrumbData>  $breadcrumbs  the trail to this page, from the registered pages; empty when it has none
     * @param  string  $title  the document title, from the same trail: the page, its workspace, the product
     */
    public function __construct(public array $product, public array $switcher, public array $navigation, public ?array $settings, public ?array $user, public array $links, public ?string $env, public array $breadcrumbs, public string $title) {}
}

<?php

namespace Tey\Kit\Features\SessionUi\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** What a guest page (sign in, register, reset) may know of the shell: the product and the footer's lines. No user, navigation, switcher, links or env. */
#[TypeScript]
final class GuestShellData extends Data
{
    /**
     * @param  array{name: string, url: string, logomark: ?string}  $product
     * @param  array{identity: string, credit: string, links: list<array{label: string, url: string}>}  $footer
     */
    public function __construct(public array $product, public array $footer, public ShellKind $kind = ShellKind::Guest) {}
}

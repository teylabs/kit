<?php

namespace Tey\Kit\Features\SessionUi\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Shell\Data\ShellKind;

/**
 * What a signed-in page outside a workspace may know of the shell: the product (name and mark) on the left, the account cluster on the right, and an
 * optional step. No switcher, navigation, breadcrumbs, workspace or footer: those belong to the app shell and never travel with this one.
 */
#[TypeScript]
final class FocusShellData extends Data
{
    /**
     * @param  array{name: string, url: string, logomark: ?string, logomark_dark: ?string, wordmark: ?string, wordmark_dark: ?string, lockup: bool}  $product
     * @param  array{name: string, email: string, initials: string, avatar_url: ?string, avatar_url_2x: ?string}  $user
     * @param  array{sign_out: string, switch_account: string}  $links
     * @param  ?array{current: int, total: int, label: string}  $step  "Step n of m", set by the page's controller; null for a page that is not a step
     */
    public function __construct(public array $product, public array $user, public array $links, public ?array $step = null, public ShellKind $kind = ShellKind::Focus) {}
}

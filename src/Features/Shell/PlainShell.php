<?php

namespace Tey\Kit\Features\Shell;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Shell\Data\ShellData;
use Tey\Kit\Features\Shell\Data\ShellKind;

/**
 * The `shell` prop of a core and shell host with no session-ui: for a guest, kind `guest` with the product and the footer and no viewer; for a
 * signed-in viewer kind `app` with their name, and no workspace, switcher or navigation. Every link is the app's home, `kit.home`, or sign-in.
 */
final class PlainShell implements ShellSource
{
    public function forRequest(Request $request): Data
    {
        $home = (string) config('kit.home', '/');
        $product = ['name' => (string) config('app.name'), 'url' => $home, 'logomark' => config('kit.brand.logomark') ?: null, 'logomark_dark' => config('kit.brand.logomark_dark') ?: null, 'wordmark' => config('kit.brand.wordmark') ?: null, 'wordmark_dark' => config('kit.brand.wordmark_dark') ?: null, 'lockup' => (bool) config('kit.brand.lockup')];
        $footer = app(Registry::class)->webFooter();
        $user = $request->user('web');
        $links = ['home' => $home, 'switcher' => $home, 'sign_in' => '/login', 'sign_out' => null];
        if (! Users::is($user)) {
            // A guest's: kind `guest`, the product and the footer, and nothing about a viewer (the fields session-ui's GuestShellData carries, without needing it).
            return new ShellData(['label' => 'Your account', 'url' => $home, 'current' => false], $product, null, null, [], null, $links, $footer, ShellKind::Guest);
        }
        $name = (string) $user->name;
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = mb_strtoupper(count($parts) > 1 ? mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1) : mb_substr($parts[0] ?? '?', 0, 2));

        return new ShellData(['label' => 'Your account', 'url' => $home, 'current' => false], $product, null, null, [], ['name' => $name, 'email' => (string) $user->email, 'initials' => $initials, 'avatar_url' => null, 'avatar_url_2x' => null, 'items' => []], $links, $footer);
    }
}

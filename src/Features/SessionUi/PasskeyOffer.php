<?php

namespace Tey\Kit\Features\SessionUi;

use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Features;

/**
 * "Add a passkey for next time", offered once after an invited sign-up, on Fortify's own passkey registration (`/user/passkeys/options`, `/user/passkeys`).
 * Only where the app has passkeys: Fortify's `passkeys` feature on and its User implementing PasskeyUser (session-ui's optional `passkeys` integration).
 * The offer is kept per person (kit_user_state) until they add a passkey or dismiss it.
 */
final class PasskeyOffer
{
    private const KEY = 'passkey.offer';

    public static function available(?object $user): bool
    {
        return $user instanceof PasskeyUser && self::enabled();
    }

    /** Whether Fortify's passkeys feature is on. */
    public static function enabled(): bool
    {
        return class_exists(Features::class) && method_exists(Features::class, 'passkeys') && Features::enabled(Features::passkeys());
    }

    /** The sign-in page's passkey endpoints (Fortify's), while the feature is on; else null. */
    public static function signIn(): ?array
    {
        return self::enabled() ? ['options_url' => url('/passkeys/login/options'), 'login_url' => url('/passkeys/login')] : null;
    }

    /**
     * Fortify's password confirmation, which its passkey registration requires (password.confirm), for the page to confirm the password before registering;
     * null when the host turns that off (fortify-options.passkeys.confirmPassword false, as Fortify reads it), so no password is asked for.
     */
    public static function confirmUrl(): ?string
    {
        return config('fortify-options.passkeys.confirmPassword', true) ? url('/user/confirm-password') : null;
    }

    /** After an invited sign-up: offer it, where passkeys are available. */
    public static function offer(object $user): void
    {
        if (self::available($user)) {
            UserState::set((int) $user->getKey(), self::KEY);
        }
    }

    public static function dismiss(int $userId): void
    {
        UserState::forget($userId, self::KEY);
    }

    /** The shared `passkeyOffer`: the registration endpoints (and confirm_url, see confirmUrl()) while the offer stands and the person has no passkey yet, else null. */
    public static function shared(?object $user): ?array
    {
        if (! self::available($user) || ! UserState::has((int) $user->getKey(), self::KEY)) {
            return null;
        }
        if (method_exists($user, 'passkeys') && $user->passkeys()->exists()) {
            self::dismiss((int) $user->getKey());

            return null;
        }

        return ['options_url' => url('/user/passkeys/options'), 'store_url' => url('/user/passkeys'), 'confirm_url' => self::confirmUrl(), 'dismiss_url' => url('/me/passkey-offer')];
    }
}

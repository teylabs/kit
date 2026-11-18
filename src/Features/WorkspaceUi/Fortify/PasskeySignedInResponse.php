<?php

namespace Tey\Kit\Features\WorkspaceUi\Fortify;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;

/**
 * Passkey sign-in straight to the person's home, like SignedInResponse: Laravel Passkeys' own response goes to `passkeys.redirect` (Fortify's login
 * redirect, copied at boot: `kit.home`), which redirected again. It reads Fortify's login redirect as the password path does. The intended URL still wins, and an app that set another home keeps it. JSON (the login page's passkey fetch) answers with the
 * `redirect` URL, in Laravel Passkeys' own shape. Bound only when laravel/passkeys is installed, in place of its own response only.
 */
final class PasskeySignedInResponse implements PasskeyLoginResponse
{
    public function toResponse($request)
    {
        $target = redirect()->intended(SignedInResponse::home($request, Fortify::redirects('login')));

        return $request->wantsJson() ? new JsonResponse(['redirect' => $target->getTargetUrl()], 200) : $target;
    }
}

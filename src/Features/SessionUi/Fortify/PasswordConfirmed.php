<?php

namespace Tey\Kit\Features\SessionUi\Fortify;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse;
use Laravel\Fortify\Fortify;
use Tey\Kit\Features\SessionUi\SafeRedirect;

/** After the password is confirmed: back to the intended URL only when it is on the app (SafeRedirect), else kit.home. Fortify's own would follow any intended URL. */
final class PasswordConfirmed implements PasswordConfirmedResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }
        $home = (string) config('kit.home', '/');
        $intended = $request->session()->pull('url.intended');

        return redirect()->to(SafeRedirect::sameOrigin(is_string($intended) ? $intended : (string) Fortify::redirects('password-confirmation'), $home));
    }
}

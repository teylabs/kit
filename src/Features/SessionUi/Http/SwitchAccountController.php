<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The FocusShell's "Switch account": end this session and go to sign in, coming back to the page that was open. The host route Concierge's switch-account flow replaces. */
final class SwitchAccountController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $back = $request->headers->get('referer');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if (is_string($back) && str_starts_with($back, url('/')) && ! str_contains($back, '/logout')) {
            $request->session()->put('url.intended', $back);
        }

        return redirect('/login');
    }
}

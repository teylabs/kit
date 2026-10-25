<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tey\Concierge\LocalAuth\LocalLogin;
use Tey\Kit\Features\Core\Users;

/**
 * Local only: the one-click sign-in the first-run demo prints for the forks that need a signed-in person. The link is signed and expires, and Concierge's own local
 * check (the application and the raw process environment are both `local`) decides whether it works at all: anywhere else it is a plain 404, whatever the link says.
 */
class DemoLoginController
{
    public function __invoke(Request $request, int $user): mixed
    {
        abort_unless(app(LocalLogin::class)->isLocalEnvironment(), 404);
        $person = Users::model()::query()->find($user);
        abort_if($person === null, 404);
        $to = (string) $request->query('to', '/');
        abort_unless(str_starts_with($to, '/') && ! str_starts_with($to, '//'), 404);
        Auth::guard('web')->login($person);
        $request->session()->regenerate();

        return redirect($to);
    }
}

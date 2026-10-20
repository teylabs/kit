<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Account\OtherSessions;
use Tey\Kit\Features\SessionUi\Account\PasswordConfirmation;

/** Sign out the sessions on your other devices (the database session driver is the only one that can list them). The password is asked for again. */
class SessionsController
{
    public function destroy(Request $request): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        PasswordConfirmation::require($request, $user);
        $ended = OtherSessions::end($user);
        abort_if($ended === null, 409, 'Signed-in devices can only be listed and ended with the database session driver.');
        $request->session()->flash('account_notice', $ended === 0 ? 'There were no other sessions.' : 'Signed out '.$ended.' other '.($ended === 1 ? 'session' : 'sessions').'.');

        return $request->expectsJson() ? response()->json(['ended' => $ended]) : back();
    }
}

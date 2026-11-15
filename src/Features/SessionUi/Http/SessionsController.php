<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Account\OtherSessions;
use Tey\Kit\Features\SessionUi\Account\PasswordConfirmation;

/** Sign out the sessions on your other devices, on every session driver but `array` (SignedInDevices). The password is asked for again. */
class SessionsController
{
    public function destroy(Request $request): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        PasswordConfirmation::require($request, $user);
        $ended = OtherSessions::end($user);
        abort_if($ended === null, 409, 'Signed-in devices can\'t be listed or ended with the array session driver.');
        $request->session()->flash('account_notice', $ended === 0 ? 'There were no other sessions.' : 'Signed out '.$ended.' other '.($ended === 1 ? 'session' : 'sessions').'.');

        return $request->expectsJson() ? response()->json(['ended' => $ended]) : back();
    }
}

<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Account\PasswordConfirmation;
use Tey\Kit\Features\SessionUi\Account\SignedInDevices;

/**
 * Settings › Security's signed-in devices: sign one of your other devices out, with the current password. Only the signed-in person's own devices are
 * found: another person's id is the same 404 as one that doesn't exist (and as one already signed out, which the page shows as a notice after its reload).
 * This device is refused: signing it out is the app's own Sign out.
 */
class DevicesController
{
    public function destroy(Request $request, int $device): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        $result = SignedInDevices::signOut($user, $device, $request, fn () => PasswordConfirmation::require($request, $user));
        if ($result['status'] === 'missing') {
            $request->session()->flash('account_notice', 'That device was already signed out.');
            abort(404);
        }
        if ($result['status'] === 'current') {
            throw ValidationException::withMessages(['device' => 'This is the device you are using now. Use Sign out on this device instead.']);
        }
        $notice = $result['at_once']
            ? 'Signed out '.$result['agent'].'.'
            : 'That device will be signed out within '.SignedInDevices::touchMinutes().' minutes.';
        if ($result['remembered']) {
            $notice .= ' It was remembered, so your other devices will ask you to sign in again next time.';
        }
        $request->session()->flash('account_notice', $notice);

        return $request->expectsJson() ? response()->json(['signed_out' => true, 'at_once' => $result['at_once']]) : back();
    }
}

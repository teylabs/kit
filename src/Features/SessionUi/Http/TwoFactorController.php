<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Account\PasswordConfirmation;

/**
 * Two-factor authentication on /me/security, through Fortify's own actions. Turning it on and off asks for the password again; the code from the
 * authenticator app turns it on for real (until then it is only waiting), and the recovery codes are shown once.
 */
class TwoFactorController
{
    /** Start: a secret and its QR code, waiting for a code. */
    public function store(Request $request, EnableTwoFactorAuthentication $enable): mixed
    {
        $user = $this->user($request);
        PasswordConfirmation::require($request, $user);
        $enable($user, true);

        return $request->expectsJson() ? response()->json(['state' => 'pending'], 201) : back();
    }

    /** Confirm: the code proves the app is set up; then it is on, and the recovery codes are shown. */
    public function update(Request $request, ConfirmTwoFactorAuthentication $confirm): mixed
    {
        $user = $this->user($request);
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        try {
            $confirm($user, str_replace(' ', '', (string) $request->input('code')));
        } catch (ValidationException $e) {
            // Fortify names an error bag for its own forms; the page's form reads the default one.
            throw ValidationException::withMessages($e->errors());
        }
        $request->session()->flash('recovery_codes', $user->fresh()->recoveryCodes());

        return $request->expectsJson() ? response()->json(['state' => 'on', 'recovery_codes' => $user->fresh()->recoveryCodes()]) : back();
    }

    public function destroy(Request $request, DisableTwoFactorAuthentication $disable): mixed
    {
        $user = $this->user($request);
        PasswordConfirmation::require($request, $user);
        $disable($user);

        return $request->expectsJson() ? response()->json(['state' => 'off']) : back();
    }

    private function user(Request $request): \Illuminate\Database\Eloquent\Model
    {
        $user = $request->user('web');
        abort_unless(Users::is($user) && method_exists($user, 'twoFactorQrCodeSvg'), 404);

        return $user;
    }
}

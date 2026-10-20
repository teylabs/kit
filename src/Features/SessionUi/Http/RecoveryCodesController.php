<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Account\PasswordConfirmation;

/** New recovery codes replace the old ones; the password is asked for again, and the new codes are shown once. */
class RecoveryCodesController
{
    public function store(Request $request, GenerateNewRecoveryCodes $generate): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user) && $user->two_factor_confirmed_at !== null, 404);
        PasswordConfirmation::require($request, $user);
        $generate($user);
        $codes = $user->fresh()->recoveryCodes();
        $request->session()->flash('recovery_codes', $codes);

        return $request->expectsJson() ? response()->json(['recovery_codes' => $codes], 201) : back();
    }
}

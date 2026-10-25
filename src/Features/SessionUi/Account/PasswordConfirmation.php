<?php

namespace Tey\Kit\Features\SessionUi\Account;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** The password asked for again before a sensitive account change (two-factor, recovery codes, signing out other sessions). */
final class PasswordConfirmation
{
    public static function require(Request $request, Authenticatable $user): void
    {
        $password = (string) $request->input('password', '');

        if ($password === '' || ! Hash::check($password, (string) $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => $password === '' ? 'Enter your password to continue.' : 'That password is not right.']);
        }
    }
}

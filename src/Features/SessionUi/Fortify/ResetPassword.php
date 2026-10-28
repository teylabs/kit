<?php

namespace Tey\Kit\Features\SessionUi\Fortify;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Tey\Kit\Features\SessionUi\PasswordPolicy;

/** Fortify's password reset, from the emailed link: a new password under the kit's policy (Password::defaults()), confirmed, with the plain breach message. */
final class ResetPassword implements ResetsUserPasswords
{
    public function reset(mixed $user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ], PasswordPolicy::messages())->validate();

        $user->forceFill(['password' => Hash::make($input['password'])])->save();
    }
}

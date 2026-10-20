<?php

namespace Tey\Kit\Features\SessionUi\Fortify;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Tey\Kit\Features\SessionUi\Account\OtherSessions;

/** Fortify's password update for the account page: the current password, then a new one, confirmed. Every other session is signed out afterwards. */
final class UpdatePassword implements UpdatesUserPasswords
{
    /** @param  array<string, mixed>  $input */
    public function update($user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ], ['current_password.current_password' => 'That is not your current password.'])->validate();

        $user->forceFill(['password' => Hash::make($input['password'])])->save();
        OtherSessions::end($user);
        session()->flash('account_notice', 'Your password was changed and your other sessions were signed out.');
    }
}

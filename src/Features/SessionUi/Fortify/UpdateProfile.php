<?php

namespace Tey\Kit\Features\SessionUi\Fortify;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Tey\Kit\Features\SessionUi\Account\SecurityLog;

/**
 * Fortify's profile update for the account page: the name, and the email address. A changed address stays unverified until the new one is confirmed (a
 * verification link is sent), and the page says so.
 */
final class UpdateProfile implements UpdatesUserProfileInformation
{
    /** @param  array<string, mixed>  $input */
    public function update($user, array $input): void
    {
        $table = $user->getTable();
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($table, 'email')->ignore($user->getKey())],
        ])->validate();
        $changed = mb_strtolower((string) $user->email) !== mb_strtolower($data['email']);

        if ($changed && $user instanceof MustVerifyEmail) {
            $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();
        } else {
            $user->forceFill(['name' => $data['name'], 'email' => $data['email']])->save();
        }

        if ($changed) {
            SecurityLog::record($user->getAuthIdentifier(), 'email_changed');
            session()->flash('account_notice', 'Your email address changed. We sent a link to the new address; until you confirm it, your access is limited.');
        }
    }
}

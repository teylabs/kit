<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\DeletePasskey;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Account\PasswordConfirmation;
use Tey\Kit\Features\SessionUi\PasskeyOffer;

/**
 * Settings › Security's passkeys: rename one, or remove one with the current password (as for two-factor). Only the signed-in person's own passkeys are
 * found: another person's id is a 404, never a 403 that would confirm it exists. Adding one is Fortify's own registration (`/user/passkeys`), as in the offer.
 */
class PasskeysController
{
    public function update(Request $request, int $passkey): mixed
    {
        $found = $this->passkey($request, $passkey);
        $name = trim((string) $request->validate(['name' => ['required', 'string', 'max:60']])['name']);
        $found->forceFill(['name' => $name])->save();

        return $request->expectsJson() ? response()->json(['id' => $found->getKey(), 'name' => $name]) : back();
    }

    public function destroy(Request $request, int $passkey, DeletePasskey $delete): mixed
    {
        $found = $this->passkey($request, $passkey);
        PasswordConfirmation::require($request, $request->user('web'));
        $delete($request->user('web'), $found);

        return $request->expectsJson() ? response()->noContent() : back();
    }

    private function passkey(Request $request, int $id): Model
    {
        $user = $request->user('web');
        abort_unless(Users::is($user) && PasskeyOffer::available($user) && method_exists($user, 'passkeys'), 404);

        return $user->passkeys()->whereKey($id)->firstOrFail();
    }
}

<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\PasskeyOffer;

/** The post-sign-up passkey offer: "Not now" (or a passkey added) closes it for good. */
class PasskeyOfferController
{
    public function destroy(Request $request): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        PasskeyOffer::dismiss((int) $user->getKey());

        return $request->expectsJson() && ! $request->header('X-Inertia') ? response()->noContent() : back();
    }
}

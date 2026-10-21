<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\ClientTimezone;
use Tey\Kit\Features\SessionUi\UserState;

/**
 * Keeps a signed-in person's saved timezone in step with their browser's, until they confirm it in the welcome flow (after which it is theirs and never overwritten).
 * It writes only when the zone changed, and remembers in the session what it last saw, so a normal request costs no query.
 */
final class RefreshTimezone
{
    public function handle(Request $request, Closure $next): mixed
    {
        $zone = ClientTimezone::fromRequest($request);
        $user = $zone === null || ! $request->hasSession() ? null : $request->user('web');
        if ($zone !== null && Users::is($user) && $request->session()->get('kit.tz') !== $zone) {
            $id = (int) $user->getKey();
            if (! UserState::has($id, 'timezone.confirmed') && UserState::get($id, 'timezone') !== $zone) {
                UserState::set($id, 'timezone', $zone);
            }
            $request->session()->put('kit.tz', $zone);
        }

        return $next($request);
    }
}

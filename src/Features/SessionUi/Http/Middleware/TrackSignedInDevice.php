<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tey\Kit\Features\SessionUi\Account\SignedInDevices;

/**
 * Keeps each signed-in session's `signed_in_devices` row, and cuts a signed-out device off (SignedInDevices). Appended to `web` after the session has
 * started. It reads the signed-in user's id from the web guard's session key, so it never loads the user and adds no `users` query; a guest's request
 * reads nothing. A signed-in request reads one revocation marker from the cache store and compares values already in the session (`kit.device`): it
 * queries the table only when the last touch is `kit.devices.touch_minutes` old, the session id changed, or the session has no row yet. A revoked
 * session, a row that is gone or holds another session or another user, fails closed: signed out, the session invalidated, and answered as an expired
 * session is (a login redirect, or a 401 for JSON). It keeps no state of its own, so it is safe in a long-lived worker (Octane).
 */
final class TrackSignedInDevice
{
    public function handle(Request $request, Closure $next): mixed
    {
        $guard = Auth::guard('web');
        if (! $request->hasSession() || ! $guard instanceof SessionGuard) {
            return $next($request);
        }
        $session = $request->session();
        $this->check($request, $session, $guard);
        $response = $next($request);
        // After the controller, before StartSession saves: the session's final id, after any regenerate (Fortify's sign-in regenerates after Login).
        $this->settle($request, $session, $guard);

        return $response;
    }

    /** Before the controller: a signed-in session that was revoked, or whose row no longer holds it, is signed out. */
    private function check(Request $request, Session $session, SessionGuard $guard): void
    {
        $userId = $session->get($guard->getName());
        if ($userId === null) {
            return;
        }
        $id = $session->getId();
        // The one read on every signed-in request: a device signed out elsewhere, or a session id a regenerate retired, is cut off on this request.
        $revoked = SignedInDevices::revoked($id);
        if ($revoked === true) {
            $this->signOut($session, $guard);
        }
        $device = $session->get(SignedInDevices::KEY);
        if (! SignedInDevices::tracked($device)) {
            // Signed in with no row yet (its sign-in request could not write one, or a session from before 0.23): written now, before the controller runs.
            $this->track($request, $session, $guard, $userId, $device) ?? $this->signOut($session, $guard);

            return;
        }
        // Another account signed in on this session: never adopts the row.
        if ((string) $device['user'] !== (string) $userId) {
            $this->signOut($session, $guard);
        }
        if ($device['sid'] !== $id) {
            // The payload under an id its row doesn't know: moved now while the row still holds the old one, else signed out.
            if (! SignedInDevices::move($device, $id)) {
                $this->signOut($session, $guard);
            }
            $session->put(SignedInDevices::KEY, ['sid' => $id, 'at' => now()->getTimestamp()] + $device);

            return;
        }
        $due = now()->getTimestamp() - (int) $device['at'] >= SignedInDevices::touchMinutes() * 60;
        // The cache store could not answer: the row in the database decides instead, on this request.
        if ($due ? ! SignedInDevices::touch($device, $id) : ($revoked === null && ! SignedInDevices::exists($device, $id))) {
            $this->signOut($session, $guard);
        }
        if ($due) {
            $session->put(SignedInDevices::KEY.'.at', now()->getTimestamp());
        }
    }

    /** After the controller: a session signed in without a row gets one; a regenerate moves the row along. */
    private function settle(Request $request, Session $session, SessionGuard $guard): void
    {
        $userId = $session->get($guard->getName());
        $device = $session->get(SignedInDevices::KEY);
        if ($userId === null) {
            if ($device !== null) {
                $session->forget(SignedInDevices::KEY);
            }

            return;
        }
        $id = $session->getId();
        if (! SignedInDevices::tracked($device)) {
            // Signed in during this request (the Login listener left `pending`), keyed by the session's final id.
            if ($this->track($request, $session, $guard, $userId, $device) === null) {
                // No row of this user holds the session: fails closed, signed out from the next request on.
                $session->forget(SignedInDevices::KEY);
                $guard->logoutCurrentDevice();
                $session->invalidate();
            }

            return;
        }
        if ($device['sid'] !== $id && (string) $device['user'] === (string) $userId) {
            // Regenerated during this request (an invitation, the demo sign-in). Not moved (the row is gone or moved): the next request touches, and signs out.
            $session->put(SignedInDevices::KEY, SignedInDevices::move($device, $id) ? ['sid' => $id] + $device : ['at' => 0] + $device);
        }
    }

    /**
     * Writes the row of a signed-in session that has none, and names it in the session. Remembered: as the sign-in said (`pending`), else (a legacy
     * session: from before 0.23, or backfilled) when the request carries the recaller cookie. Null when no row of this user holds the session; a legacy
     * session of a person who has signed a device out is never given a new one.
     */
    private function track(Request $request, Session $session, SessionGuard $guard, int|string $userId, mixed $device): ?int
    {
        $pending = is_array($device) && isset($device['pending']);
        $remembered = $pending ? (bool) ($device['remembered'] ?? false) : $request->cookies->has($guard->getRecallerName());
        $row = SignedInDevices::track($request, $session->getId(), $userId, $remembered, legacy: ! $pending);
        if ($row !== null) {
            $session->put(SignedInDevices::KEY, ['id' => $row, 'user' => $userId, 'sid' => $session->getId(), 'at' => now()->getTimestamp()]);
        }

        return $row;
    }

    /** Signs the request out as an expired session is: the session ended, then the login redirect (or a 401 for JSON) the exception handler gives. */
    private function signOut(Session $session, SessionGuard $guard): never
    {
        $session->forget(SignedInDevices::KEY);
        $guard->logoutCurrentDevice();
        $session->invalidate();
        $session->regenerateToken();

        throw new AuthenticationException('Unauthenticated.', ['web']);
    }
}

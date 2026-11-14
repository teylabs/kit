<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\ClientClock;
use Tey\Kit\Features\SessionUi\ClientTimezone;
use Tey\Kit\Features\SessionUi\UserState;

/**
 * `PUT /me/profile/clock`: Account › Profile's Date & time. The person's own clock, `12h` or `24h`, or `workspace` to follow the workspace's again, and
 * their time zone when sent (saved and confirmed, as the welcome step confirms it). The `tz` cookie is set to the saved zone too, so the pages that follow
 * show dates and times in it at once: the viewer's zone reads that cookie first (ClientTimezone::forViewer). It is the browser's cookie, written as
 * syncTimezoneCookie() writes it, so a page load that syncs it puts the browser's own zone back.
 */
class ClockController
{
    public function update(Request $request): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        $data = $request->validate(['clock' => ['required', Rule::in(['workspace', '12h', '24h'])], 'timezone' => ['sometimes', 'string', 'timezone']], ['clock.required' => 'Choose a time format.', 'clock.in' => 'Choose a time format.', 'timezone.string' => 'Choose a time zone from the list.', 'timezone.timezone' => 'Choose a time zone from the list.']);
        $clock = (string) $data['clock'];
        ClientClock::save((int) $user->getKey(), $clock === 'workspace' ? null : $clock);
        $zone = isset($data['timezone']) ? (string) $data['timezone'] : null;
        if ($zone !== null) {
            UserState::set((int) $user->getKey(), 'timezone', $zone);
            UserState::set((int) $user->getKey(), 'timezone.confirmed');
        }
        Toast::success('Date & time saved.');

        // Unencrypted (session-ui leaves `tz` out of EncryptCookies) and readable by the page's script, with the attributes syncTimezoneCookie() uses.
        return $zone === null ? back() : back()->withCookie(cookie(ClientTimezone::COOKIE, $zone, 525600, '/', null, $request->isSecure(), false, false, 'lax'));
    }
}

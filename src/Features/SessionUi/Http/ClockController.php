<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\ClientClock;
use Tey\Kit\Features\SessionUi\UserState;

/**
 * `PUT /me/profile/clock`: Account › Profile's Date & time. The person's own clock, `12h` or `24h`, or `workspace` to follow the workspace's again, and
 * their time zone when sent (saved and confirmed, as the welcome step confirms it).
 */
class ClockController
{
    public function update(Request $request): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        $data = $request->validate(['clock' => ['required', Rule::in(['workspace', '12h', '24h'])], 'timezone' => ['sometimes', 'string', 'timezone']], ['clock.*' => 'Choose a time format.', 'timezone.*' => 'Choose a time zone from the list.']);
        $clock = (string) $data['clock'];
        ClientClock::save((int) $user->getKey(), $clock === 'workspace' ? null : $clock);
        if (isset($data['timezone'])) {
            UserState::set((int) $user->getKey(), 'timezone', (string) $data['timezone']);
            UserState::set((int) $user->getKey(), 'timezone.confirmed');
        }
        Toast::success('Date & time saved.');

        return back();
    }
}

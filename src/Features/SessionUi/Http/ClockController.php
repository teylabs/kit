<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\ClientClock;

/** `PUT /me/profile/clock`: the person's own clock, `12h` or `24h`, or `workspace` to follow the workspace's again (Account › Profile's Clock card). */
class ClockController
{
    public function update(Request $request): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        $clock = (string) $request->validate(['clock' => ['required', Rule::in(['workspace', '12h', '24h'])]], ['clock.*' => 'Choose a clock.'])['clock'];
        ClientClock::save((int) $user->getKey(), $clock === 'workspace' ? null : $clock);
        Toast::success('Clock saved.');

        return back();
    }
}

<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Clock;

/**
 * The viewer's clock, 12- or 24-hour, for the `kitClock` share. A person's own choice is kept in user_state as `clock` (`12h` or `24h`; none means the
 * workspace's); the resolution is core's Clock: the person's, then the workspace's (Registry::workspaceClockUsing), then `kit.clock.default`, `auto` by default.
 */
final class ClientClock
{
    public const KEY = 'clock';

    /** The person's own choice, or null when they use the workspace's. */
    public static function own(int $userId): ?string
    {
        return Clock::valid(UserState::get($userId, self::KEY));
    }

    /** Saves the person's choice, `12h` or `24h`; anything else (null) forgets it, so the workspace's applies again. */
    public static function save(int $userId, ?string $clock): void
    {
        $clock = Clock::valid($clock);
        $clock === null ? UserState::forget($userId, self::KEY) : UserState::set($userId, self::KEY, $clock);
    }

    /** `12h`, `24h` or `auto` for the signed-in person, or for a guest the workspace's and then the default. */
    public static function forViewer(Request $request): string
    {
        return Clock::resolve($request->user('web'));
    }
}

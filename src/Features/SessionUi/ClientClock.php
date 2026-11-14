<?php

namespace Tey\Kit\Features\SessionUi;

use Tey\Kit\Features\Core\Clock;

/**
 * A person's own clock, 12- or 24-hour, kept in user_state as `clock` (`12h` or `24h`; none means the workspace's). Core's Clock reads it through
 * Registry::userClock and resolves the rest: the workspace's (Registry::workspaceClockUsing), then `kit.clock.default`, `auto` by default.
 */
final class ClientClock
{
    public const KEY = 'clock';

    /** The person's own choice, or null when they use the workspace's. */
    public static function own(int $userId): ?string
    {
        return Clock::valid(UserState::get($userId, self::KEY));
    }

    /** Saves the person's choice, `12h` or `24h`; anything else (null) forgets it, so the workspace's applies again. The next read in this request sees it. */
    public static function save(int $userId, ?string $clock): void
    {
        $clock = Clock::valid($clock);
        $clock === null ? UserState::forget($userId, self::KEY) : UserState::set($userId, self::KEY, $clock);
        Clock::forget();
    }
}

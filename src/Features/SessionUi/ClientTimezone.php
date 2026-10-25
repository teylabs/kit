<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;

/**
 * The viewer's timezone. The browser keeps its current zone in the `tz` cookie (set by a small script, readable by the server without decryption), and nothing from it
 * is trusted: it must be one of PHP's own zone identifiers. Resolution: a valid cookie, then the zone saved for the person, then the workspace's, then UTC.
 */
final class ClientTimezone
{
    public const COOKIE = 'tz';

    /** The identifier if it is a real zone, else null: the raw value never reaches Carbon, `now()` or storage. */
    public static function valid(mixed $zone): ?string
    {
        static $known = null;
        $known ??= array_flip(timezone_identifiers_list());

        return is_string($zone) && isset($known[$zone]) ? $zone : null;
    }

    public static function fromRequest(Request $request): ?string
    {
        return self::valid($request->cookies->get(self::COOKIE));
    }

    /** @param  ?string  $workspace  the workspace's own zone, when known */
    public static function get(Request $request, ?int $userId = null, ?string $workspace = null): string
    {
        return self::fromRequest($request) ?? ($userId === null ? null : self::valid(UserState::get($userId, 'timezone'))) ?? self::valid($workspace) ?? 'UTC';
    }
}

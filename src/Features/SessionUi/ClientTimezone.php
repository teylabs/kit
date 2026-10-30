<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Registry;
use Throwable;

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

    /**
     * The viewer's zone for the `kitTimezone` share: the signed-in person's (or a guest's cookie), then the current workspace's when the app registers one
     * (Registry::workspaceTimezone, read inside the tenant), then UTC. A failure reading the workspace's zone falls back rather than failing the page.
     */
    public static function forViewer(Request $request): string
    {
        $user = $request->user('web');
        $workspace = null;
        try {
            $zone = app(Registry::class)->workspaceTimezone();
            $workspace = $zone === null ? null : ($zone['get'])();
        } catch (Throwable) {
            $workspace = null;
        }

        return self::get($request, $user === null ? null : (int) $user->getKey(), is_string($workspace) ? $workspace : null);
    }
}

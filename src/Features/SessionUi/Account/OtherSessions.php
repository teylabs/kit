<?php

namespace Tey\Kit\Features\SessionUi\Account;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Tey\Concierge\Support\UserSessions;

/** A user's signed-in devices, listed and ended on every session driver but `array` (tests only), from `signed_in_devices` (SignedInDevices). */
final class OtherSessions
{
    public static function supported(): bool
    {
        return config('session.driver') !== 'array';
    }

    /**
     * Each device last seen within the session lifetime plus `kit.devices.touch_minutes`, most recent first (50). `agent`, `ip`, `last_active` and
     * `current` are the keys the page had before 0.23 (`ip` is now the network's prefix); `url` signs the device out, null for this one.
     *
     * @return list<array{agent: string, ip: ?string, last_active: string, current: bool, id: int, signed_in_at: string, country: ?string, remembered: bool, url: ?string}>
     */
    public static function list(Authenticatable $user, string $currentId): array
    {
        if (! self::supported()) {
            return [];
        }
        $current = SignedInDevices::hashes($currentId);

        return array_map(function (object $row) use ($current): array {
            $isCurrent = in_array($row->session_hash, $current, true);

            return [
                'agent' => SecurityLog::describeDevice($row->browser, $row->platform),
                'ip' => $row->ip_prefix,
                'last_active' => Carbon::parse($row->last_seen_at)->toIso8601String(),
                'current' => $isCurrent,
                'id' => (int) $row->id,
                'signed_in_at' => Carbon::parse($row->created_at)->toIso8601String(),
                'country' => $row->country,
                'remembered' => (bool) $row->remembered,
                'url' => $isCurrent ? null : url('/me/security/devices/'.$row->id),
            ];
        }, SignedInDevices::live($user->getAuthIdentifier()));
    }

    /** Ends every session of the user but this one, and cycles the remember-me token; returns how many ended, or null when sessions cannot be listed. */
    public static function end(Authenticatable $user): ?int
    {
        UserSessions::cycleRememberToken($user);
        if (method_exists($user, 'save')) {
            $user->save();
        }
        if (! self::supported()) {
            return null;
        }
        $ended = SignedInDevices::endAll($user, request()->session()->getId());
        SecurityLog::record($user->getAuthIdentifier(), 'sessions_ended');

        return $ended;
    }
}

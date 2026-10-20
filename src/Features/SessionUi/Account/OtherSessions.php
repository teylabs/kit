<?php

namespace Tey\Kit\Features\SessionUi\Account;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Tey\Concierge\Support\UserSessions;

/** A user's sessions on other devices: listed and ended with the database session driver, which is the only one that can name a user's sessions. */
final class OtherSessions
{
    public static function supported(): bool
    {
        return config('session.driver') === 'database';
    }

    /** @return list<array{agent: string, ip: ?string, last_active: string, current: bool}> */
    public static function list(Authenticatable $user, string $currentId): array
    {
        if (! self::supported()) {
            return [];
        }

        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())->orderByDesc('last_activity')->limit(50)->get()
            ->map(fn (object $row): array => ['agent' => SecurityLog::describeAgent($row->user_agent ?? null), 'ip' => $row->ip_address ?? null, 'last_active' => now()->setTimestamp((int) $row->last_activity)->toIso8601String(), 'current' => $row->id === $currentId])->all();
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
        $ended = DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())->where('id', '!=', request()->session()->getId())->delete();
        SecurityLog::record($user->getAuthIdentifier(), 'sessions_ended');

        return $ended;
    }
}

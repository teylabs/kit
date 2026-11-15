<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Support\Facades\DB;
use Tey\Kit\Features\Core\Kit;
use Tey\Kit\Features\Core\RequestMemo;

/**
 * What the kit remembers about a person (`user_state`): own state (workspace 0) or state that belongs to one workspace. Server-side, so it follows them across devices.
 * Within a web request every key a person has in one workspace (or their own) is read in one query, once (RequestMemo), and get(), has() and many() answer from it;
 * set(), forget() and forgetAll() empty it, so the next read in the request sees the write.
 */
final class UserState
{
    public static function get(int $userId, string $key, int $workspaceId = 0): ?string
    {
        $value = self::all($userId, $workspaceId)[$key] ?? null;

        return $value === null ? null : (string) $value;
    }

    /**
     * Several keys in one query: key => value for each key that is set (its value may be null), in the order asked. A key that is not set is left out, so
     * `array_key_exists()` answers has() and `?? null` answers get() for each.
     *
     * @param  list<string>  $keys
     * @return array<string, ?string>
     */
    public static function many(int $userId, array $keys, int $workspaceId = 0): array
    {
        if ($keys === []) {
            return [];
        }
        $values = self::all($userId, $workspaceId);
        $found = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $values)) {
                $found[$key] = $values[$key] === null ? null : (string) $values[$key];
            }
        }

        return $found;
    }

    public static function has(int $userId, string $key, int $workspaceId = 0): bool
    {
        return array_key_exists($key, self::all($userId, $workspaceId));
    }

    public static function set(int $userId, string $key, ?string $value = '1', int $workspaceId = 0): void
    {
        DB::table('user_state')->upsert([['user_id' => $userId, 'workspace_id' => $workspaceId, 'key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]], ['user_id', 'workspace_id', 'key'], ['value', 'updated_at']);
        RequestMemo::instance()->flush();
    }

    public static function forget(int $userId, string $key, int $workspaceId = 0): void
    {
        DB::table('user_state')->where(['user_id' => $userId, 'workspace_id' => $workspaceId, 'key' => $key])->delete();
        RequestMemo::instance()->flush();
    }

    /**
     * Every key the person has in the workspace (0: their own), key => value: one query, once per request.
     *
     * @return array<string, mixed>
     */
    private static function all(int $userId, int $workspaceId): array
    {
        return RequestMemo::instance()->get('user_state:'.$userId.':'.$workspaceId, fn (): array => DB::table('user_state')->where(['user_id' => $userId, 'workspace_id' => $workspaceId])->pluck('value', 'key')->all());
    }

    /** Everything remembered about a person: it goes with their account. Without session-ui there is nothing; with it, a missing table throws (#967). */
    public static function forgetAll(int $userId): void
    {
        if (Kit::present('session-ui', 'user_state')) {
            DB::table('user_state')->where('user_id', $userId)->delete();
            RequestMemo::instance()->flush();
        }
    }

    /** The person's timezone, or the application's when they have none. */
    public static function timezone(int $userId): string
    {
        return self::get($userId, 'timezone') ?? (string) config('app.timezone', 'UTC');
    }
}

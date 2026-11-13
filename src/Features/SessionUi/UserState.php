<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Support\Facades\DB;
use Tey\Kit\Features\Core\Kit;

/** What the kit remembers about a person (`user_state`): own state (workspace 0) or state that belongs to one workspace. Server-side, so it follows them across devices. */
final class UserState
{
    public static function get(int $userId, string $key, int $workspaceId = 0): ?string
    {
        $value = DB::table('user_state')->where(['user_id' => $userId, 'workspace_id' => $workspaceId, 'key' => $key])->value('value');

        return $value === null ? null : (string) $value;
    }

    public static function has(int $userId, string $key, int $workspaceId = 0): bool
    {
        return DB::table('user_state')->where(['user_id' => $userId, 'workspace_id' => $workspaceId, 'key' => $key])->exists();
    }

    public static function set(int $userId, string $key, ?string $value = '1', int $workspaceId = 0): void
    {
        DB::table('user_state')->upsert([['user_id' => $userId, 'workspace_id' => $workspaceId, 'key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]], ['user_id', 'workspace_id', 'key'], ['value', 'updated_at']);
    }

    public static function forget(int $userId, string $key, int $workspaceId = 0): void
    {
        DB::table('user_state')->where(['user_id' => $userId, 'workspace_id' => $workspaceId, 'key' => $key])->delete();
    }

    /** Everything remembered about a person: it goes with their account. Without session-ui there is nothing; with it, a missing table throws (#967). */
    public static function forgetAll(int $userId): void
    {
        if (Kit::present('session-ui', 'user_state')) {
            DB::table('user_state')->where('user_id', $userId)->delete();
        }
    }

    /** The person's timezone, or the application's when they have none. */
    public static function timezone(int $userId): string
    {
        return self::get($userId, 'timezone') ?? (string) config('app.timezone', 'UTC');
    }
}

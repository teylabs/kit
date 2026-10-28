<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Core\Data\WorkbenchLayout;
use Tey\Kit\Features\Core\Workbench\WorkbenchKey;
use Tey\Kit\Features\Core\Workbench\WorkbenchPreferences;

/**
 * A person's Workbench layouts in `kit_user_state` (their own state, workspace 0), one row per workbench key holding `{ layout, version }`. A save names the version
 * it read; a different stored version refuses it (stale), so two devices never silently overwrite each other's layout. Layout state only, never content.
 */
final class UserWorkbenchPreferences implements WorkbenchPreferences
{
    public function layout(int $userId, string $key): WorkbenchLayout
    {
        $route = Route::has('me.workbenches.update') ? ['url' => route('me.workbenches.update', ['key' => WorkbenchKey::assert($key)], false), 'method' => 'put'] : null;

        return new WorkbenchLayout($this->stored($userId, $key), $route);
    }

    public function save(int $userId, string $key, array $layout, int $version): array
    {
        $name = self::name($key);

        return DB::transaction(function () use ($userId, $key, $name, $layout, $version): array {
            // The row is locked while the version is compared, so two saves of one version cannot both win.
            DB::table('kit_user_state')->where(['user_id' => $userId, 'workspace_id' => 0, 'key' => $name])->lockForUpdate()->value('id');
            $stored = $this->stored($userId, $key);
            $current = $stored['version'] ?? 0;
            if ($version !== $current) {
                return ['saved' => false, 'layout' => $stored['layout'] ?? null, 'version' => $current];
            }
            UserState::set($userId, $name, json_encode(['layout' => $layout, 'version' => $current + 1], JSON_THROW_ON_ERROR));

            return ['saved' => true, 'layout' => $layout, 'version' => $current + 1];
        });
    }

    /** @return ?array{layout: array<string, mixed>|null, version: int} */
    private function stored(int $userId, string $key): ?array
    {
        $raw = UserState::get($userId, self::name($key));
        $decoded = $raw === null ? null : json_decode($raw, true);
        if (! is_array($decoded) || ! is_int($decoded['version'] ?? null)) {
            return null;
        }

        return ['layout' => is_array($decoded['layout'] ?? null) ? $decoded['layout'] : null, 'version' => $decoded['version']];
    }

    private static function name(string $key): string
    {
        return 'workbench.'.WorkbenchKey::assert($key);
    }
}

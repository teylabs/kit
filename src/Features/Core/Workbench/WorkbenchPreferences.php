<?php

namespace Tey\Kit\Features\Core\Workbench;

use Tey\Kit\Features\Core\Data\WorkbenchLayout;

/**
 * Where a person's Workbench layout is remembered. Core binds a null store (nothing is remembered on the server); session-ui binds one on `kit_user_state` with its
 * preference endpoint. `layout()` is what a WorkbenchViewModel hands its page.
 */
interface WorkbenchPreferences
{
    /** The person's layout for the workbench key, and where to save it; null `state` before the first save, null `preference` when it cannot be saved. */
    public function layout(int $userId, string $key): WorkbenchLayout;

    /**
     * Saves `layout` when `version` is the stored one, and returns `['saved' => bool, 'layout' => ..., 'version' => int]`: the new version when saved, the stored
     * layout and version when the write was stale.
     *
     * @param  array<string, mixed>  $layout
     * @return array{saved: bool, layout: array<string, mixed>|null, version: int}
     */
    public function save(int $userId, string $key, array $layout, int $version): array;
}

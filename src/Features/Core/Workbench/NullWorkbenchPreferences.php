<?php

namespace Tey\Kit\Features\Core\Workbench;

use Tey\Kit\Features\Core\Data\WorkbenchLayout;

/** Without session-ui there is no per-person store: nothing is remembered on the server, and a save is refused (the page keeps its layout in the browser). */
final class NullWorkbenchPreferences implements WorkbenchPreferences
{
    public function layout(int $userId, string $key): WorkbenchLayout
    {
        return new WorkbenchLayout(null, null);
    }

    public function save(int $userId, string $key, array $layout, int $version): array
    {
        return ['saved' => false, 'layout' => null, 'version' => 0];
    }
}

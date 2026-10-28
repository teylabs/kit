<?php

namespace Tey\Kit\Features\Settings\Adapters;

use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Settings\Operations\WorkspaceProfile;
use Tey\Kit\Features\Workspaces\Workspaces;

final class SettingsUi
{
    public function show(Principal $principal, int $workspaceId): array
    {
        return Workspaces::query()->findOrFail($workspaceId)->execute(function () use ($principal): array {
            return app(WorkspaceProfile::class)->read($principal)->toArray();
        });
    }

    public function update(Principal $principal, int $workspaceId, array $input): array
    {
        return Workspaces::query()->findOrFail($workspaceId)->execute(function () use ($principal, $input): array {
            return app(WorkspaceProfile::class)->update($principal, $input)->toArray();
        });
    }
}

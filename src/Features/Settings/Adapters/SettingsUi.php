<?php

namespace Tey\Kit\Features\Settings\Adapters;

use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Settings\Operations\WorkspaceProfile;
use Tey\Kit\Features\Workspaces\TenantBoundary;

final class SettingsUi
{
    public function show(Principal $principal, int $workspaceId): array
    {
        return app(TenantBoundary::class)->run($workspaceId, fn () => app(WorkspaceProfile::class)->read($principal)->toArray());
    }

    public function update(Principal $principal, int $workspaceId, array $input): array
    {
        return app(TenantBoundary::class)->run($workspaceId, fn () => app(WorkspaceProfile::class)->update($principal, $input)->toArray());
    }
}

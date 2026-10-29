<?php

namespace Tey\Kit\Features\Settings\Integrations\Mcp;

use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Settings\Operations\WorkspaceProfile;
use Tey\Kit\Features\Workspaces\Workspaces;

final class SettingsMcp
{
    // Connection identity comes from authenticated transport, never tool input.
    public function call(Principal $principal, int $workspaceId, string $tool, array $arguments = []): array
    {
        if ($principal->connectionId === null) {
            throw new \RuntimeException('connection_required');
        }

        return Workspaces::run($workspaceId, function () use ($principal, $tool, $arguments): array {
            $operation = app(WorkspaceProfile::class);

            return match ($tool) {
                'workspace_settings_get' => $arguments === [] ? $operation->read($principal)->toArray() : throw new \RuntimeException('unknown_arguments'),
                'workspace_settings_update' => $operation->update($principal, $arguments)->toArray(),
                default => throw new \RuntimeException('unknown_tool'),
            };
        });
    }
}

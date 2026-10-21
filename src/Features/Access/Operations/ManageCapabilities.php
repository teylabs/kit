<?php

namespace Tey\Kit\Features\Access\Operations;

use Illuminate\Support\Facades\DB;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;

/** What a platform operator does with capabilities: switch one on or off for a workspace or a person in it, or clear the override. Audited with a reason, never by a connection. */
final class ManageCapabilities
{
    public function set(Principal $operator, string $capability, int $workspaceId, ?int $userId, ?bool $enabled, string $reason): void
    {
        if ($operator->connectionId !== null || ! DB::table('operators')->where('actor_id', $operator->actorId)->exists() || Users::find($operator->actorId) === null) {
            throw new \RuntimeException('operator_required');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('reason_required');
        }
        $workspace = Workspace::query()->findOrFail($workspaceId);
        $capabilities = app(Capabilities::class);
        app(TenantBoundary::class)->run($workspaceId, function () use ($capabilities, $capability, $workspace, $userId, $enabled): void {
            $userId === null ? $capabilities->setForWorkspace($capability, $workspace, $enabled) : $capabilities->setForPerson($capability, $workspace, $userId, $enabled);
        });
        app(Audit::class)->write($operator->actorId, $workspaceId, 'capability.changed', ['capability' => $capability, 'user_id' => $userId, 'enabled' => $enabled, 'reason' => $reason]);
    }
}

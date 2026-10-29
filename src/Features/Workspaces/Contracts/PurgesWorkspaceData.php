<?php

namespace Tey\Kit\Features\Workspaces\Contracts;

use Tey\Concierge\Contracts\Workspace;

/**
 * The host's purge of its own data for a workspace being deleted. Required to turn `kit.lifecycle.workspace_delete` on: without a binding the deletion refuses.
 * Runs inside the deletion's transaction, under the workspace lock, BEFORE the kit's and Concierge's rows go; throwing rolls the whole deletion back.
 * A host with nothing of its own to purge binds a handler that does nothing, explicitly.
 */
interface PurgesWorkspaceData
{
    public function purge(Workspace $workspace): void;
}

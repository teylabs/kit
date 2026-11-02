<?php

namespace Tey\Kit\Features\Workspaces\Contracts;

use Tey\Concierge\Contracts\Workspace;

/**
 * Opts a host's purge handler into deferred deletion. Bound as PurgesWorkspaceData, a handler that also implements this is never run in the request: the kit
 * closes the workspace at once (`deleting_at`: no one admitted, no new members, listed nowhere), writes the audit, and then calls begin(), outside any
 * transaction of the kit's, for the host to queue its own job. That job purges the host's data in its own chunks and, last, calls
 * `app(FinishWorkspaceDeletion::class)($workspaceId)`, which deletes the kit's and Concierge's rows in one short transaction. purge() is not called by the kit
 * in this mode. A closed workspace is never reopened: a begin() that throws leaves it closed, for the host to dispatch again. See docs/lifecycle-events.md.
 */
interface DefersWorkspacePurge extends PurgesWorkspaceData
{
    public function begin(Workspace $workspace): void;
}

<?php

namespace Tey\Kit\Features\Mcp;

use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/**
 * A tool that is absent from the list when the capability behind its ability is off for this person in this workspace, so an assistant never sees what the workspace does
 * not have. Declare the ability in `ABILITY`; a tool called anyway is still refused by Access.
 */
trait GatedByCapability
{
    public function shouldRegister(): bool
    {
        $capability = app(Registry::class)->capabilityFor(static::ABILITY);
        $workspace = Workspace::current();
        if ($capability === null || $workspace === null) {
            return true;
        }
        $user = request()->user();

        return app(Capabilities::class)->active($capability, $workspace, Users::is($user) ? $user : null);
    }
}

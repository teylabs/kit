<?php

namespace Tey\Kit\Features\Mcp;

use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Entitlements;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/**
 * A tool that is absent from the list when the capability behind its ability is off for this person in this workspace, or its plan leaves it out, so an assistant never sees what the workspace does
 * not have. Name the capability with `#[Capability]` (see `Registry::capabilityTools()`), or declare an ability in `ABILITY`; a tool called anyway is still refused.
 */
trait GatedByCapability
{
    public function shouldRegister(): bool
    {
        return $this->unavailable() === null;
    }

    /** Why the tool is absent here (`capability_unavailable` or `entitlement_unavailable`), or null while it is present. */
    public function unavailable(): ?string
    {
        $capability = Capability::of($this)?->capability ?? (defined(static::class.'::ABILITY') ? app(Registry::class)->capabilityFor(static::ABILITY) : null);
        $workspace = Workspace::current();
        if ($capability === null || $workspace === null) {
            return null;
        }
        $user = request()->user();
        if (! app(Capabilities::class)->active($capability, $workspace, Users::is($user) ? $user : null)) {
            return 'capability_unavailable';
        }

        return app(Entitlements::class)->included($capability, $workspace) ? null : 'entitlement_unavailable';
    }
}

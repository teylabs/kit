<?php

namespace Tey\Kit\Features\Mcp;

use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Entitlements;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Workspaces;

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

    /**
     * Why the tool is absent here, or null while it is present: `capability_unavailable` or `entitlement_unavailable` for its capability (the module switch), then
     * `permission_denied` when the person's roles do not allow its `ABILITY` (the permission). A person who is not an active member is left to the operation to refuse.
     */
    public function unavailable(): ?string
    {
        $capability = Capability::of($this)?->capability ?? (defined(static::class.'::ABILITY') ? app(Registry::class)->capabilityFor(static::ABILITY) : null);
        $workspace = Workspaces::current();
        if ($capability === null || $workspace === null) {
            return null;
        }
        $user = request()->user();
        if (! app(Capabilities::class)->active($capability, $workspace, Users::is($user) ? $user : null)) {
            return 'capability_unavailable';
        }
        if (! app(Entitlements::class)->included($capability, $workspace)) {
            return 'entitlement_unavailable';
        }
        $member = Users::is($user) ? RequestMemo::membership($user, $workspace) : null;
        if (defined(static::class.'::ABILITY') && $member?->isActive() && ! app(Access::class)->permits($user, $member, static::ABILITY, $workspace)) {
            return 'permission_denied';
        }

        return null;
    }
}

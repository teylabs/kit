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
 * A tool (or resource) that is absent from the list when the capability behind its ability is off for this person in this workspace, or its plan leaves it out, so an assistant never sees what the workspace does
 * not have. Name the capability with `#[Capability]` (see `Registry::capabilityTools()`), a `capabilityGate()` method (a descriptor resource), or declare an ability in `ABILITY`; a tool called anyway is still refused.
 */
trait GatedByCapability
{
    public function shouldRegister(): bool
    {
        return $this->unavailable() === null;
    }

    /**
     * Why the tool is absent here, or null while it is present: `capability_unavailable` or `entitlement_unavailable` for its capability (the module switch), `connection_denied`
     * when the calling connection does not cover its ability (a narrow connection), `scope_required` for an explicit tool whose scope the consent did not name, then
     * `permission_denied` when the person's roles do not allow its `ABILITY` (the permission). A person who is not an active member is left to the operation to refuse.
     */
    public function unavailable(): ?string
    {
        $capability = Capability::of($this)?->capability ?? (method_exists($this, 'capabilityGate') ? $this->capabilityGate() : null) ?? (defined(static::class.'::ABILITY') ? app(Registry::class)->capabilityFor(static::ABILITY) : null);
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
        // Off means absent: the tool is present only when the calling connection covers the ability it is admitted under, the same rule the call path applies, so a
        // narrow connection never sees a tool it could not call. An explicit tool's scope must be named (acts-as-me never covers it): `scope_required`; any other
        // ability the connection does not cover: `connection_denied`. No connection at all (not an MCP request) covers nothing.
        $attribute = Capability::of($this);
        $ability = $attribute?->scope ?? (defined(static::class.'::ABILITY') ? static::ABILITY : null);
        if ($ability !== null) {
            $principal = request()->attributes->get('mcp.principal');
            $held = $principal instanceof \Tey\Kit\Features\Core\Principal && $principal->connectionId !== null
                ? (array) json_decode((string) \Illuminate\Support\Facades\DB::table('connections')->where('id', $principal->connectionId)->whereNull('revoked_at')->value('scopes'), true)
                : [];
            if (! \Tey\Kit\Features\Mcp\Operations\WorkspaceConnection::covers($held, $ability)) {
                return $attribute !== null && $attribute->explicit() ? 'scope_required' : 'connection_denied';
            }
        }
        $member = Users::is($user) ? RequestMemo::membership($user, $workspace) : null;
        if (defined(static::class.'::ABILITY') && $member?->isActive() && ! app(Access::class)->permits($user, $member, static::ABILITY, $workspace)) {
            return 'permission_denied';
        }

        return null;
    }
}

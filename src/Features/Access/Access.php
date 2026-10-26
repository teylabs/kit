<?php

namespace Tey\Kit\Features\Access;

use Illuminate\Support\Facades\DB;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Workspaces\Models\Workspace;

final class Access
{
    public function authorize(Principal $principal, string $ability, bool $paid = false): Workspace
    {
        $capability = app(Registry::class)->capabilityFor($ability);
        if ($capability === null) {
            throw new \RuntimeException('permission_denied');
        }
        $workspace = Workspace::required();
        $user = RequestMemo::user($principal->actorId);
        $concierge = app(Concierge::class);
        $member = $user ? RequestMemo::membership($user, $workspace) : null;
        if (! $member?->isActive()) {
            throw new \RuntimeException('workspace_not_found');
        }
        if (! RequestMemo::account($user)->isActive()) {
            throw new \RuntimeException('account_inactive');
        }
        if (! $user->conciergeHasVerifiedEmail()) {
            throw new \RuntimeException('verification_required');
        }
        if (! $workspace->admitted) {
            throw new \RuntimeException('admission_required');
        }
        // An ability a feature ties to a permission is decided by the member's roles there; any other write stays owner-only.
        $permission = app(Registry::class)->permissionFor($ability);
        if ($permission !== null ? ! app(Permissions::class)->granted($user, $permission, $workspace) : (str_ends_with($ability, '.write') && ! $concierge->isOwner($member))) {
            throw new \RuntimeException('permission_denied');
        }
        if ($principal->connectionId !== null) {
            app(Registry::class)->guardConnection($principal, $workspace, $ability);
        }
        if (! app(Capabilities::class)->active($capability, $workspace, $user)) {
            throw new \RuntimeException('capability_unavailable');
        }
        // On here, but not in the workspace's plan: absent, or refused with an upgrade the entitlement opted into.
        if (! app(Entitlements::class)->included($capability, $workspace)) {
            throw new \RuntimeException(app(Registry::class)->entitlementFor($capability)['upgrade'] === null ? 'entitlement_unavailable' : 'entitlement_required');
        }
        if ($paid && ! $this->entitled($workspace)) {
            throw new \RuntimeException('entitlement_required');
        }

        return $workspace;
    }

    public function entitled(Workspace $workspace): bool
    {
        return DB::table('entitlements')->where('workspace_id', $workspace->id)->where('kind', 'paid')->whereNull('revoked_at')->exists()
            || DB::table('complimentary_grants')->where('workspace_id', $workspace->id)->whereNull('revoked_at')->where('expires_at', '>', now())->exists();
    }
}

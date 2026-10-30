<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\GrantPolicy;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\HiddenGrants;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\SessionUi\ViewModels\ChangeRoleViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\InviteMemberViewModel;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * Settings › People's server-driven modals: a page rendered in a dialog, with its own URL, the same authorisation as any page, and the People list as the base page it opens over
 * (opened directly, the URL renders People with the modal on top). The opener's URL is the descriptor the People page ships.
 */
class PeopleModalController
{
    public function create(Request $request, int $workspace): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace): mixed {
            $actor = $request->user();
            abort_unless(Users::is($actor), 401);
            $tenant = Workspaces::required();
            Gate::forUser($actor)->authorize('manageMembers', $tenant);
            $roles = array_map(fn (string $role): array => ['value' => $role, 'label' => (string) config('concierge.roles.'.$role.'.label', ucfirst($role))], ManagePeople::memberRoles(app(GrantPolicy::class)->grantableRoles($actor, $tenant)));
            // Nobody to grant a role to: nothing to invite as, so the modal is refused like any page the viewer cannot use.
            abort_if($roles === [], 403);
            $url = WorkspaceUrl::to($workspace, 'settings/people/invitations', 'session-ui');

            return Inertia::modal('Workspace/Settings/People/Invite/Page', (new InviteMemberViewModel('Invite member', $roles, new Action('Send invitation', $url, 'post')))->toArray())->baseUrl(WorkspaceUrl::to($workspace, 'settings/people', 'session-ui'));
        });
    }

    /** Change a member's role: refused unless Concierge's rank rules let the viewer manage that member (the same check that decides whether the row offers it). */
    public function edit(Request $request, int $workspace, int $membership): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace, $membership): mixed {
            $actor = $request->user();
            abort_unless(Users::is($actor), 401);
            $tenant = Workspaces::required();
            Gate::forUser($actor)->authorize('manageMembers', $tenant);
            // A member hidden by kit.people.hide_grants is absent here too: a 404, as for no such member.
            $found = ConciergeModels::membership()::query()->with('user')->where('workspace_id', $workspace)->tap(fn ($query) => HiddenGrants::scope($query, $workspace))->findOrFail($membership);
            $policy = app(GrantPolicy::class);
            abort_unless($found->status === MembershipStatus::Active && $policy->authorizeManagement($actor, $tenant, $found)->allowed(), 403);
            $roles = array_map(fn (string $role): array => ['value' => $role, 'label' => (string) config('concierge.roles.'.$role.'.label', ucfirst($role))], ManagePeople::memberRoles($policy->grantableRoles($actor, $tenant)));
            abort_if($roles === [], 403);
            $url = WorkspaceUrl::to($workspace, 'settings/people/members/'.$found->getKey(), 'session-ui');

            return Inertia::modal('Workspace/Settings/People/ChangeRole/Page', (new ChangeRoleViewModel('Change role for '.$found->user->name, app(Concierge::class)->roleNames($found)[0] ?? null, $roles, new Action('Save', $url, 'put')))->toArray())->baseUrl(WorkspaceUrl::to($workspace, 'settings/people', 'session-ui'));
        });
    }
}

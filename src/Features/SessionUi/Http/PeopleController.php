<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\GrantPolicy;
use Tey\Concierge\Enums\InvitationStatus;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\Roles;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\ViewModels\PeopleViewModel;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** Settings › People: who belongs to the workspace and the invitations still open, a server-driven list (search and sort through the query string). */
class PeopleController
{
    public function index(Request $request, int $workspace): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace): mixed {
            $actor = $request->user();
            abort_unless(Users::is($actor), 401);
            $tenant = Workspace::required();
            Gate::forUser($actor)->authorize('manageMembers', $tenant);
            $concierge = app(Concierge::class);
            $policy = app(GrantPolicy::class);
            $users = (new (ConciergeModels::user()))->getTable();
            $memberships = QueryBuilder::for(ConciergeModels::membership()::query()->join($users, $users.'.id', '=', 'concierge_memberships.user_id')->where('concierge_memberships.workspace_id', $workspace)->where('concierge_memberships.status', MembershipStatus::Active->value)->select('concierge_memberships.*')->with('user'), $request)
                ->allowedFilters(AllowedFilter::callback('search', fn ($query, $value) => $query->where(fn ($inner) => $inner->where($users.'.name', 'like', '%'.addcslashes((string) $value, '%_\\').'%')->orWhere($users.'.email', 'like', '%'.addcslashes((string) $value, '%_\\').'%'))))
                ->allowedSorts(AllowedSort::field('name', $users.'.name'), AllowedSort::field('joined', 'concierge_memberships.joined_at'))
                ->defaultSort('joined', 'concierge_memberships.id')
                ->get();
            $label = fn (string $role): string => (string) config('concierge.roles.'.$role.'.label', ucfirst($role));
            $shell = app(PageShell::class);
            $owner = ($mine = $concierge->membership($actor, $tenant)) !== null && $concierge->isOwner($mine);
            $members = $memberships->map(function ($membership) use ($owner, $concierge, $policy, $actor, $tenant, $workspace, $label, $shell): array {
                $role = $concierge->roleNames($membership)[0] ?? '';
                $user = $membership->user;

                return ['id' => (int) $membership->getKey(), 'name' => (string) $user->name, 'email' => (string) $user->email, 'initials' => $shell->initials((string) $user->name), 'avatar' => Avatars::urls((int) $user->getKey()), 'role' => $role, 'role_label' => $label($role), 'joined_at' => $membership->joined_at?->toIso8601String(), 'is_you' => (int) $user->getKey() === (int) $actor->getKey(), 'transferable' => $owner && (int) $user->getKey() !== (int) $actor->getKey() && $user->conciergeHasVerifiedEmail(), 'manageable' => $policy->authorizeManagement($actor, $tenant, $membership)->allowed(), 'update_url' => WorkspaceUrl::to($workspace, 'settings/people/members/'.$membership->getKey(), 'session-ui'), 'destroy_url' => WorkspaceUrl::to($workspace, 'settings/people/members/'.$membership->getKey(), 'session-ui')];
            })->values()->all();
            $invitations = ConciergeModels::invitation()::query()->with('inviter')->where('workspace_id', $workspace)->where('status', InvitationStatus::Pending->value)->orderBy('id')->get()->map(function ($invitation) use ($label, $workspace): array {
                $role = $invitation->roleNames()[0] ?? '';

                return ['id' => (string) $invitation->public_id, 'email' => (string) $invitation->email, 'role' => $role, 'role_label' => $label($role), 'expires_at' => $invitation->expires_at->toIso8601String(), 'expired' => $invitation->isExpired(), 'invited_by' => $invitation->inviter?->name, 'resend_url' => WorkspaceUrl::to($workspace, 'settings/people/invitations/'.$invitation->public_id, 'session-ui'), 'revoke_url' => WorkspaceUrl::to($workspace, 'settings/people/invitations/'.$invitation->public_id, 'session-ui')];
            })->values()->all();
            $roles = array_map(fn (string $role): array => ['value' => $role, 'label' => $label($role)], $policy->grantableRoles($actor, $tenant));
            $model = new PeopleViewModel('People', $members, $invitations, $roles, $roles !== [], WorkspaceUrl::to($workspace, 'settings/people/invitations', 'session-ui'), (string) ($request->query('filter')['search'] ?? ''), (string) $request->query('sort', ''), $request->session()->get('invite_link'), $owner, WorkspaceUrl::to($workspace, 'settings/people/ownership', 'session-ui'));
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json($model);
            }

            return Inertia::render('Workspace/Settings/People/Page', $model);
        });
    }
}

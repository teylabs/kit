<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Tey\Concierge\Contracts\GrantPolicy;
use Tey\Concierge\Support\MembersIndex;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\HiddenGrants;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\PeopleTable;
use Tey\Kit\Features\SessionUi\ViewModels\PeopleViewModel;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * Settings › People: Concierge's MembersIndex (the tabs, the query, the counts and each row's actions, by its rank rules) rendered in the kit's DataTable. This
 * controller tells it where each action goes and reshapes its table; the kit has no membership query and no per-row policy of its own.
 */
class PeopleController
{
    public function index(Request $request, int $workspace): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace): mixed {
            $actor = $request->user();
            abort_unless(Users::is($actor), 401);
            $tenant = Workspaces::required();
            Gate::forUser($actor)->authorize('manageMembers', $tenant);
            $people = fn (string $suffix = ''): string => WorkspaceUrl::to($workspace, 'settings/people'.$suffix, 'session-ui');
            // Members holding a grant the app hides (kit.people.hide_grants) are left out of the rows and the count alike.
            $props = HiddenGrants::index(app(MembersIndex::class)->urlsUsing(fn (string $action, ?Model $subject): ?string => match ($action) {
                'index' => $people(),
                'invite' => $people('/invitations'),
                'member.updateGrants', 'member.remove' => $people('/members/'.$subject->getKey()),
                // Make owner: the one place the transfer is offered (Concierge adds it only for the current owner, on someone who is not the owner). It opens the
                // Transfer ownership modal, and only on a member who may receive it: verified, active, never a scoped guest.
                'member.transferOwnership' => ManagePeople::transferable($subject) ? $people('/members/'.$subject->getKey().'/transfer') : null,
                'invitation.resend', 'invitation.revoke' => $people('/invitations/'.$subject->public_id),
                default => null,
            }))->props($actor, $tenant, $request);
            $label = fn (string $role): string => (string) config('concierge.roles.'.$role.'.label', ucfirst($role));
            $roles = array_map(fn (string $role): array => ['value' => $role, 'label' => $label($role)], ManagePeople::memberRoles(app(GrantPolicy::class)->grantableRoles($actor, $tenant)));
            $invite = $props->metadata->links->invite === null ? null : new Action('Invite member', $props->metadata->links->invite, 'get');
            $model = new PeopleViewModel('People', $props->tab, PeopleTable::from($props->table, app(PageShell::class)), $invite, $roles, $request->session()->get('invite_link'));
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json($model);
            }

            return Inertia::render('Workspace/Settings/People/Page', $model);
        });
    }
}

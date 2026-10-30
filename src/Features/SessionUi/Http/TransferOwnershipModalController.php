<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\HiddenGrants;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\SessionUi\ViewModels\TransferOwnershipViewModel;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** Settings › People's Transfer ownership modal, a server-driven page over People like Change role. The transfer it sends goes to OwnershipController. */
class TransferOwnershipModalController
{
    /** Transfer ownership to a member: the owner only (an admin gets 403), and only to someone who may receive it (never a scoped guest). */
    public function edit(Request $request, int $workspace, int $membership): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace, $membership): mixed {
            $actor = $request->user();
            abort_unless(Users::is($actor), 401);
            $tenant = Workspaces::required();
            ManagePeople::memberOrAbsent($actor);
            Gate::forUser($actor)->authorize('manageMembers', $tenant);
            ManagePeople::make()->authorizeOwner($actor);
            // A member hidden by kit.people.hide_grants is absent here too: a 404, as for no such member.
            $found = ConciergeModels::membership()::query()->with('user')->where('workspace_id', $workspace)->tap(fn ($query) => HiddenGrants::scope($query, $workspace))->findOrFail($membership);
            abort_unless(ManagePeople::transferable($found), 403);
            $demoted = (string) config('concierge.transfer_demotes_to', 'admin');
            $url = WorkspaceUrl::to($workspace, 'settings/ownership', 'session-ui');

            return Inertia::modal('Workspace/Settings/People/Transfer/Page', (new TransferOwnershipViewModel('Transfer ownership to '.$found->user->name, (int) $found->getKey(), (string) $found->user->name, (string) $tenant->name, (string) config('concierge.roles.'.$demoted.'.label', ucfirst($demoted)), new Action('Transfer ownership', $url, 'put')))->toArray())->baseUrl(WorkspaceUrl::to($workspace, 'settings/people', 'session-ui'));
        });
    }
}

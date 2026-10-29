<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Tey\Concierge\Concierge;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\SessionUi\ViewModels\DeleteWorkspaceViewModel;
use Tey\Kit\Features\Workspaces\Operations\DeleteWorkspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** Settings › Delete workspace: the owner only (an admin gets 403, a non-member 404), with the workspace's name typed to confirm. */
class WorkspaceDeletionController
{
    public function show(Request $request, int $workspace): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace): mixed {
            $actor = $request->user();
            abort_unless(Users::is($actor), 401);
            $tenant = Workspaces::required();
            ManagePeople::memberOrAbsent($actor);
            Gate::forUser($actor)->authorize('manageMembers', $tenant);
            $this->owner($actor, $workspace);
            $members = ConciergeModels::membership()::query()->where('workspace_id', $workspace)->where('status', MembershipStatus::Active)->count();

            return Inertia::render('Workspace/Settings/Delete/Page', new DeleteWorkspaceViewModel('Delete workspace', (string) $tenant->name, $members, new Action('Delete workspace', WorkspaceUrl::to($workspace, 'settings/delete', 'session-ui'), 'delete', destructive: true)));
        });
    }

    public function destroy(Request $request, int $workspace): mixed
    {
        $confirmation = (string) $request->validate(['confirmation' => ['required', 'string', 'max:255']])['confirmation'];
        $actor = $request->user();
        abort_unless(Users::is($actor), 401);
        app(TenantBoundary::class)->run($workspace, fn () => DeleteWorkspace::run($actor, $confirmation));

        return $request->expectsJson() ? response()->json(['deleted' => true, 'workspace_id' => $workspace]) : redirect('/me/workspaces');
    }

    private function owner(mixed $actor, int $workspace): void
    {
        $membership = ConciergeModels::membership()::query()->where('workspace_id', $workspace)->where('user_id', $actor->getKey())->first();
        abort_unless($membership !== null && $membership->status === MembershipStatus::Active && app(Concierge::class)->isOwner($membership), 403);
    }
}

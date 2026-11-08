<?php

namespace Tey\Kit\Features\Workspaces\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;
use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\Workspaces\ViewModels\IndexWorkspaceViewModel;
use Tey\Kit\Features\Workspaces\WorkspaceChooser;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** The workspaces a user belongs to: the chooser, at `/me/workspaces`. */
class WorkspaceController
{
    public function index(Request $request): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $found = QueryBuilder::for(Workspaces::query()->memberOf($user), $request)->allowedSorts('name')->defaultSort('name', 'id')->get(['id', 'name', 'slug']);
        // Role, member count and ownership for the whole list in a few queries, only for workspaces the person is an active member of.
        $memberships = WorkspaceChooser::memberships($user, $found->map(fn (Workspace $workspace): int => (int) $workspace->id)->all());
        $found = $found->filter(fn (Workspace $workspace): bool => isset($memberships[(int) $workspace->id]))->values();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['workspaces' => $found->map(fn (Workspace $workspace): array => ['id' => $workspace->id, 'name' => $workspace->name, 'leave' => $this->leave($workspace, $memberships[(int) $workspace->id])])->all()]);
        }
        if (($open = app(Registry::class)->opensWorkspace($request)) !== null) {
            return redirect($open);
        }
        // Each workspace opens where the shell would land in it (the first page the person may open); none means there is nothing there for them to open.
        $shell = app(PageShell::class);
        $urls = collect($shell->choices($request))->pluck('url', 'id')->all();
        $current = WorkspaceUrl::currentMemberId($request);
        $workspaces = $found->map(fn (Workspace $workspace): array => [
            'id' => (int) $workspace->id,
            'name' => (string) $workspace->name,
            'initials' => $shell->initials((string) $workspace->name),
            'url' => $urls[(int) $workspace->id] ?? null,
            'role' => WorkspaceChooser::roleLabel($memberships[(int) $workspace->id]['roles'][0] ?? null),
            'members' => $memberships[(int) $workspace->id]['members'],
            'current' => $current === (int) $workspace->id,
            'leave' => $this->leave($workspace, $memberships[(int) $workspace->id]),
        ])->all();
        // Invitations only to an email the person has verified: never what was sent to an address they have not shown is theirs.
        $invitations = method_exists($user, 'conciergeHasVerifiedEmail') && $user->conciergeHasVerifiedEmail() ? WorkspaceChooser::invitations((string) $user->email) : [];

        return Inertia::render('Workspace/Index/Page', new IndexWorkspaceViewModel('Your workspaces', $workspaces, $invitations, WorkspaceChooser::createAction('New workspace')));
    }

    /**
     * Leave, for a workspace you belong to and do not own: with a confirmation. The owner has to transfer first, so for them it is not there at all.
     *
     * @param  array{membership: int, roles: list<string>, owner: bool, members: int}  $membership
     */
    private function leave(Workspace $workspace, array $membership): ?Action
    {
        $url = '/me/workspaces/'.$workspace->slug.'/membership';

        return $membership['owner'] ? null : new Action('Leave', $url, 'delete', confirm: ['title' => 'Leave '.$workspace->name.'?', 'description' => 'You lose access straight away. An admin can invite you again.', 'label' => 'Leave workspace']);
    }
}

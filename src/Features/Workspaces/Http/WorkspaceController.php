<?php

namespace Tey\Kit\Features\Workspaces\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\ViewModels\IndexWorkspaceViewModel;

/** The workspaces a user belongs to: the chooser, at `/me/workspaces`. */
class WorkspaceController
{
    public function index(Request $request): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $workspaces = QueryBuilder::for(Workspace::query()->memberOf($user), $request)->allowedSorts('name')->defaultSort('name', 'id')->get(['id', 'name', 'slug'])->map(fn (Workspace $workspace) => ['id' => $workspace->id, 'name' => $workspace->name, 'leave' => $this->leave($user, $workspace)])->all();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['workspaces' => $workspaces]);
        }
        if (($open = app(Registry::class)->opensWorkspace($request)) !== null) {
            return redirect($open);
        }

        return Inertia::render('Workspace/Index/Page', new IndexWorkspaceViewModel('Your workspaces', $workspaces));
    }

    /** Leave, for a workspace you belong to and do not own: with a confirmation. The owner has to transfer first, so for them it is not there at all. */
    private function leave(mixed $user, Workspace $workspace): ?Action
    {
        $concierge = app(Concierge::class);
        if (($membership = $concierge->membership($user, $workspace)) === null) {
            return null;
        }
        $url = '/me/workspaces/'.$workspace->slug.'/membership';

        return $concierge->isOwner($membership) ? null : new Action('Leave', $url, 'delete', confirm: ['title' => 'Leave '.$workspace->name.'?', 'description' => 'You lose access straight away. An admin can invite you again.', 'label' => 'Leave workspace']);
    }
}

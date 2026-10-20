<?php

namespace Tey\Kit\Features\Workspaces\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;
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
        $workspaces = QueryBuilder::for(Workspace::query()->memberOf($user), $request)->allowedSorts('name')->defaultSort('name', 'id')->get(['id', 'name'])->map(fn (Workspace $workspace) => ['id' => $workspace->id, 'name' => $workspace->name])->all();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['workspaces' => $workspaces]);
        }
        if (($open = app(Registry::class)->opensWorkspace($request)) !== null) {
            return redirect($open);
        }

        return Inertia::render('Workspace/Index/Page', new IndexWorkspaceViewModel('Your workspaces', $workspaces));
    }
}

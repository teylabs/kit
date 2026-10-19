<?php

namespace Tey\Kit\Features\Workspaces\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;

class WorkspaceController
{
    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }

    /** Where an accepted invitation lands: `kit.invitation_redirect` (a full route name taking `workspace`), else the first available navigation route, else the workspace list. */
    private function landing(int $workspace): string
    {
        $route = config('kit.invitation_redirect');
        if (is_string($route) && $route !== '') {
            return route($route, ['workspace' => $workspace]);
        }
        foreach (app(Registry::class)->navigationLinks() as $link) {
            $route = config('kit.routes.'.$link['suffix'].'.name', '').'workspaces.'.$link['suffix'].'.index';
            if (Route::has($route)) {
                return route($route, ['workspace' => $workspace]);
            }
        }

        return route(config('kit.routes.workspaces.name', '').'workspaces.index');
    }

    public function index(Request $request): mixed
    {
        $workspaces = Workspace::all()->filter(fn (Workspace $workspace) => app(Concierge::class)->membership($request->user(), $workspace)?->isActive())->map(fn ($workspace) => ['id' => $workspace->id, 'name' => $workspace->name])->values()->all();

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json(['workspaces' => $workspaces]) : Inertia::render('Workspaces', compact('workspaces'));
    }

    public function accept(Request $request): mixed
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        $result = app(AcceptWorkspaceInvitation::class)->handle($this->principal($request), $data['token']);

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($result) : redirect($this->landing((int) $result['workspace_id']));
    }
}

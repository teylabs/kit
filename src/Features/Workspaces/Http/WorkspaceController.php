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
use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Tey\Kit\Features\Workspaces\ViewModels\IndexWorkspaceViewModel;

class WorkspaceController
{
    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }

    /** Where an accepted invitation lands: `kit.invitation_redirect` (a full route name taking `workspace`), else the first available primary navigation page, else the workspace list. */
    private function landing(Request $request, int $workspace): string
    {
        $route = config('kit.invitation_redirect');
        if (is_string($route) && $route !== '') {
            return route($route, ['workspace' => $workspace]);
        }
        foreach (array_filter(app(Registry::class)->navigationLinks(), fn (array $link) => $link['slot'] === 'primary') as $link) {
            $route = config('kit.routes.'.$link['suffix'].'.name', '').'workspaces.'.$link['suffix'].'.index';
            if (Route::has($route)) {
                return url(WorkspaceUrl::to($workspace, $link['suffix'], $link['suffix'], $request));
            }
        }

        return route(config('kit.routes.workspaces.name', '').'workspaces.index');
    }

    public function index(Request $request): mixed
    {
        $workspaces = Workspace::all()->filter(fn (Workspace $workspace) => app(Concierge::class)->membership($request->user(), $workspace)?->isActive())->map(fn ($workspace) => ['id' => $workspace->id, 'name' => $workspace->name])->values()->all();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['workspaces' => $workspaces]);
        }
        if (($open = app(Registry::class)->opensWorkspace($request)) !== null) {
            return redirect($open);
        }

        return Inertia::render('Workspace/Index/Page', new IndexWorkspaceViewModel('Your workspaces', $workspaces));
    }

    public function accept(Request $request): mixed
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        $result = app(AcceptWorkspaceInvitation::class)->handle($this->principal($request), $data['token']);

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($result) : redirect($this->landing($request, (int) $result['workspace_id']));
    }

    /** The old workspace list URL, permanently moved to `/me/workspaces`. */
    public function movedIndex(): mixed
    {
        return redirect('/me/workspaces', 301);
    }

    /** The old `/workspaces/{id or slug}/...` URL, permanently moved to `/@{slug}/...`. */
    public function moved(Request $request, string $workspace, ?string $path = null): mixed
    {
        $found = Workspace::query()->where('slug', $workspace)->when(ctype_digit($workspace), fn ($query) => $query->orWhere('id', (int) $workspace))->first() ?? throw new \RuntimeException('workspace_not_found');
        $query = $request->getQueryString();

        return redirect(WorkspaceUrl::full($found, (string) $path).($query === null ? '' : '?'.$query), 301);
    }
}

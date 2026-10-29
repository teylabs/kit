<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\ClientTimezone;
use Tey\Kit\Features\SessionUi\Dashboard\Widgets;
use Tey\Kit\Features\SessionUi\Data\WelcomeCardData;
use Tey\Kit\Features\SessionUi\UserState;
use Tey\Kit\Features\SessionUi\ViewModels\DashboardViewModel;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** A workspace's home: where sign-in lands. The registered widgets the viewer may see, or an empty state when there are none. */
class DashboardController
{
    public function show(Request $request, int $workspace): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        app(Access::class)->authorize(new Principal((int) $user->getKey()), 'dashboard.read');
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['workspace' => ['id' => $workspace, 'name' => Workspaces::required()->name]]);
        }
        Onboarding::context((int) $user->getKey(), $workspace);
        // Someone who just joined goes through the welcome steps before their first dashboard: they come back here when it is over.
        if (UserState::has((int) $user->getKey(), 'onboarding.pending') && ! UserState::has((int) $user->getKey(), 'onboarding.done')) {
            if (Onboarding::next((int) $user->getKey()) !== null) {
                $request->session()->put(WelcomeController::SESSION_NEXT, $request->fullUrl());

                return redirect('/welcome');
            }
            UserState::set((int) $user->getKey(), 'onboarding.done');
        }
        $component = app(Registry::class)->dashboardComponent();
        $principal = new Principal((int) $user->getKey());
        $resolved = app(Widgets::class)->resolve($request, $principal, $workspace, DashboardViewModel::propMethods(DashboardViewModel::class));

        // Rendered here, inside the tenant context: a list's deferred rows load in the partial request that asks for them, and must see this workspace.
        return Inertia::render($component ?? 'Workspace/Dashboard/Page', new DashboardViewModel('Dashboard', (string) Workspaces::required()->name, 'Open settings', WorkspaceUrl::to($workspace, 'settings', 'session-ui', $request), $this->greeting($request, $user), $this->welcome($user, $workspace), $resolved['widgets'], $resolved['lists']))->toResponse($request);
    }

    /** "Good morning, Jasper", by the hour in the person's own timezone; before the browser has sent its zone, the workspace's. */
    private function greeting(Request $request, object $user): string
    {
        $workspaceZone = app(Registry::class)->workspaceTimezone();
        $hour = (int) now(ClientTimezone::get($request, (int) $user->getKey(), $workspaceZone === null ? null : ($workspaceZone['get'])()))->format('G');
        $first = trim(strtok(trim((string) $user->name), ' ') ?: '');

        return ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening')).($first === '' ? '' : ', '.$first);
    }

    /** The one welcome: the greeting and the first-run checklist, until the person dismisses it. Dismiss is offered only when no item is still pending. */
    private function welcome(object $user, int $workspace): ?WelcomeCardData
    {
        $id = (int) $user->getKey();
        if (UserState::has($id, 'welcome.dismissed', $workspace)) {
            return null;
        }
        $items = Onboarding::items($id);
        $checklist = array_map(fn (array $item): array => ['id' => $item['id'], 'label' => $item['label'], 'hint' => $item['hint'], 'url' => '/welcome/'.$item['id'], 'state' => $item['state']], $items);
        $pending = array_filter($items, fn (array $item): bool => $item['state'] === 'pending') !== [];
        $first = trim(strtok(trim((string) $user->name), ' ') ?: '');

        return new WelcomeCardData('Welcome to '.config('app.name').($first === '' ? '' : ', '.$first).'.', 'Your workspace for '.Workspaces::required()->name.' is ready.', $pending ? null : new Action('Dismiss welcome', '/me/welcome/'.$workspace, 'delete'), $checklist, count(array_filter($items, fn (array $item): bool => $item['state'] === 'done')), count($items));
    }
}

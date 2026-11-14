<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\ClientTimezone;
use Tey\Kit\Features\SessionUi\Dashboard\Widgets;
use Tey\Kit\Features\SessionUi\SessionUiRoutes;
use Tey\Kit\Features\SessionUi\UserState;
use Tey\Kit\Features\SessionUi\ViewModels\DashboardViewModel;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;
use Tey\Kit\Features\Workspaces\FirstPage;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** A workspace's home: where sign-in lands. The registered widgets the viewer may see, or an empty state when there are none. */
class DashboardController
{
    public function show(Request $request, int $workspace): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        if (($elsewhere = $this->withoutDashboard($request, $user, $workspace)) !== null) {
            return $elsewhere;
        }
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['workspace' => ['id' => $workspace, 'name' => Workspaces::required()->name]]);
        }
        Onboarding::context((int) $user->getKey(), $workspace);
        // Someone who just joined goes through the welcome steps before their first dashboard: they come back here when it is over.
        if (SessionUiRoutes::on('welcome') && UserState::has((int) $user->getKey(), 'onboarding.pending') && ! UserState::has((int) $user->getKey(), 'onboarding.done')) {
            if (Onboarding::next((int) $user->getKey()) !== null) {
                $request->session()->put(WelcomeController::SESSION_NEXT, $request->fullUrl());

                return redirect('/welcome');
            }
            UserState::set((int) $user->getKey(), 'onboarding.done');
        }
        // The checklists' rows open /welcome/{id}: that flow asks a workspace step about this workspace (access already passed, so the viewer is a member here).
        if (SessionUiRoutes::on('welcome')) {
            $request->session()->put(WelcomeController::SESSION_WORKSPACE, $workspace);
        }
        $component = app(Registry::class)->dashboardComponent() ?? 'Workspace/Dashboard/Page';
        $principal = new Principal((int) $user->getKey());
        $reserved = DashboardViewModel::propMethods(DashboardViewModel::class);
        // The reload after a Sort by choice names it in the request itself (X-Kit-Sort, the widget's token and the order: the PUT's redirect carries it). It
        // builds only that widget and merges its frame into the page's by key, so the other cards keep theirs, but only while the viewer still sees exactly
        // the widgets the page was drawn with and the widget shows the chosen order. Otherwise (a widget hidden or added since, another tab's later choice on
        // the same widget) every widget is sent, replacing them, since a merge by key can't take a card away. Nothing is kept between requests.
        $sort = $this->sortChoice($request, $component);
        $only = $sort['widget'] ?? null;
        $resolved = app(Widgets::class)->resolve($request, $principal, $workspace, $reserved, $only);
        if ($only !== null && ($resolved['set'] !== $sort['set'] || count($resolved['widgets']) !== 1 || ($resolved['widgets'][0]['sortBy']['current'] ?? null) !== $sort['sort'])) {
            $only = null;
            $resolved = app(Widgets::class)->resolve($request, $principal, $workspace, $reserved);
        }

        // Rendered here, inside the tenant context: a list's deferred rows load in the partial request that asks for them, and must see this workspace.
        return Inertia::render($component, new DashboardViewModel('Dashboard', (string) Workspaces::required()->name, 'Open settings', WorkspaceUrl::to($workspace, 'settings', 'session-ui', $request), $this->greeting($request, $user), $resolved['widgets'], $resolved['lists'], (string) config('kit.dashboard.aside', 'narrow'), mergeWidgets: $only !== null))->toResponse($request);
    }

    /**
     * The Sort by choice a partial reload of `widgets` for this page follows, from its X-Kit-Sort header (`widget=…&set=…&sort=…`), or null: none, malformed,
     * another component, or a reload that resets `widgets` (one frame alone would replace the rest).
     *
     * @return ?array{widget: string, set: string, sort: string}
     */
    private function sortChoice(Request $request, string $component): ?array
    {
        $headers = fn (string $name): array => explode(',', (string) $request->header($name));
        if ($request->header('X-Inertia-Partial-Component') !== $component || ! in_array('widgets', $headers('X-Inertia-Partial-Data'), true)
            || in_array('widgets', $headers('X-Inertia-Reset'), true) || strlen((string) $request->header('X-Kit-Sort')) > 512) {
            return null;
        }
        parse_str((string) $request->header('X-Kit-Sort'), $choice);
        foreach (['widget', 'set', 'sort'] as $part) {
            if (! is_string($choice[$part] ?? null) || $choice[$part] === '') {
                return null;
            }
        }

        return ['widget' => $choice['widget'], 'set' => $choice['set'], 'sort' => $choice['sort']];
    }

    /**
     * Authorises dashboard.read. When the workspace has the dashboard capability off (and only then: every other refusal stands), a page view of the root
     * opens the viewer's first primary navigation page they may open, else the workspace list; null means show the dashboard.
     */
    private function withoutDashboard(Request $request, object $user, int $workspace): mixed
    {
        $principal = new Principal((int) $user->getKey());
        try {
            app(Access::class)->authorize($principal, 'dashboard.read');

            return null;
        } catch (RuntimeException $refused) {
            // Access checks membership, account, verification, admission and the role before the capability, so a capability refusal means all of those passed.
            $capability = app(Registry::class)->capabilityFor('dashboard.read');
            if ($refused->getMessage() !== 'capability_unavailable' || $capability === null || app(Capabilities::class)->active($capability, Workspaces::required(), $user)
                || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                throw $refused;
            }
        }

        return redirect(FirstPage::url($workspace, $request, function (string $ability) use ($principal): bool {
            try {
                app(Access::class)->authorize($principal, $ability);

                return true;
            } catch (RuntimeException $exception) {
                if (! in_array($exception->getMessage(), ['workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'workspace_deleting', 'permission_denied', 'capability_unavailable', 'entitlement_unavailable', 'entitlement_required'], true)) {
                    throw $exception;
                }

                return false;
            }
        }) ?? '/me/workspaces');
    }

    /** "Good morning, Jasper", by the hour in the person's own timezone; before the browser has sent its zone, the workspace's. */
    private function greeting(Request $request, object $user): string
    {
        $workspaceZone = app(Registry::class)->workspaceTimezone();
        $hour = (int) now(ClientTimezone::get($request, (int) $user->getKey(), $workspaceZone === null ? null : ($workspaceZone['get'])()))->format('G');
        $first = trim(strtok(trim((string) $user->name), ' ') ?: '');

        return ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening')).($first === '' ? '' : ', '.$first);
    }
}

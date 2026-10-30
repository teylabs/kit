<?php

namespace Tey\Kit\Features\WorkspaceUi;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Dashboard\ActivityWidget;
use Tey\Kit\Features\SessionUi\Dashboard\PeopleWidget;
use Tey\Kit\Features\SessionUi\Dashboard\YouWidget;
use Tey\Kit\Features\SessionUi\HiddenGrants;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\PageTitle;
use Tey\Kit\Features\SessionUi\PlaceholderRoutes;
use Tey\Kit\Features\SessionUi\Search\PeopleSearch;
use Tey\Kit\Features\SessionUi\Search\Searches;
use Tey\Kit\Features\SessionUi\ViewModels\ErrorViewModel;

/**
 * The workspace pages session-ui owned before the split: the kit's home and the dashboard, search, the settings and account landing pages, People,
 * the account pages (`/me`), the placeholders, the error page and the no-workspace page. Their classes keep their `SessionUi` names, and their
 * routes keep session-ui's names and `kit.routes.session-ui` options (the composition root passes them), so a host that had them sees the same routes. Requires session-ui, which
 * is the sign-in alone without it.
 */
final class WorkspaceUiProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        // A user with exactly one reachable workspace has nothing to choose on the list: it opens that workspace at its first page.
        $registry->routes(__DIR__.'/routes/workspace.php', 'web', $routes)
            ->navigation('dashboard.read', 'Dashboard', '', order: -10, active: '')
            ->abilities(['dashboard.read' => 'workspace.dashboard', 'members.manage' => 'workspace.settings', Searches::ABILITY => 'workspace.search'])
            // Global search is opt-in: workspace.search is off until `kit.capabilities` (or an operator, per workspace) switches it on.
            // members.manage is gated on workspace.settings, so workspace-ui declares it too: a host without the settings feature still has People.
            ->capabilities(['workspace.dashboard', 'workspace.search', 'workspace.settings'])
            // The narrow token scope a connection asks for to read the dashboard over MCP (Dashboard\ReadDashboard), as settings.read is for settings.
            ->scopes(['dashboard.read' => 'Read the workspace dashboard', Searches::ABILITY => 'Search the workspace'])
            ->settingsSection('workspace', 'people', 'People', 'Who belongs to this workspace, their roles, and who is invited.', 'members.manage', 'Workspace', 0, 'users')
            ->settingsSection('personal', 'profile', 'Profile', 'Your name, email address and photo.', null, 'Account', -20, 'user')
            ->settingsSection('personal', 'password', 'Password', 'Change your password.', null, 'Account', -15, 'key-round')
            ->settingsSection('personal', 'security', 'Security', 'Two-factor authentication, signed-in devices and your security log.', null, 'Account', -10, 'shield-check')
            // The danger zone, last in each list. Delete workspace is never listed (no card, no sidebar entry): General offers it to the owner at its foot, and only the owner may open it (an admin gets 403).
            ->settingsSection('workspace', 'delete', 'Delete workspace', 'Delete this workspace and everything in it, for everyone.', 'members.manage', 'Danger zone', 1000)
            ->settingsSection('personal', 'delete', 'Delete account', 'Delete your account and leave every workspace.', null, 'Danger zone', 1000);
        // The kit's generic widgets, each optional: `kit.dashboard.widgets` lists the ones the app keeps (all three by default).
        $kept = config('kit.dashboard.widgets', ['you', 'activity', 'people']);
        foreach (['you' => [YouWidget::class, -30, 'dashboard.read'], 'activity' => [ActivityWidget::class, -20, 'members.manage'], 'people' => [PeopleWidget::class, -10, 'dashboard.read']] as $name => [$widget, $order, $ability]) {
            if (in_array($name, (array) $kept, true)) {
                $registry->widget('kit.'.$name, $widget, $order, $ability);
            }
        }
        // The kit's one generic search provider: the workspace's members. `kit.search.people` false leaves it out.
        if (config('kit.search.people', true)) {
            $registry->searchProvider('kit.people', PeopleSearch::class, 'dashboard.read', 'People', 100);
        }
        // Over its per-minute searches a person gets a 429, and over MCP the `rate_limited` refusal.
        $registry->errors([Searches::RATE_LIMITED => 429], [Searches::RATE_LIMITED => 'Too many searches. Wait a minute and try again.'])->mcpRefusalCodes([Searches::RATE_LIMITED]);
        $registry->workspaceOpener(function (Request $request): ?string {
            $reachable = array_values(array_filter(app(PageShell::class)->choices($request), fn (array $choice) => $choice['url'] !== null));

            return count($reachable) === 1 ? $reachable[0]['url'] : null;
        });
        $registry->errorPages(function (string $code, int $status, ?string $message, Request $request) {
            // The error names its page once: the heading and the document title are the same label.
            $label = match ($status) {
                404 => 'Page not found',
                403 => 'Not allowed',
                409 => 'Out of date',
                422 => 'Check your input',
                default => 'Something went wrong',
            };
            PageTitle::name($label, $request);

            return Inertia::render('Workspace/Error/Page', new ErrorViewModel($label, $status, $message ?? 'This request is unavailable. Your access or the resource may have changed.', '/me/workspaces', 'Return to your workspaces'))->toResponse($request)->setStatusCode($status);
        });
    }

    public function boot(): void
    {
        // Hidden grants this Concierge can't apply stop the app here, never on a page (HiddenGrants::assertUsable()).
        HiddenGrants::assertUsable();
    }

    public function register(): void
    {
        // What has been declared as placeholder routes is remembered per application, never per PHP process.
        $this->app->singleton(PlaceholderRoutes::class);
    }
}

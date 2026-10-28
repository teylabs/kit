<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Fortify\Fortify;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Defaults;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\ViewModels\PageLayouts;
use Tey\Kit\Features\Core\Workbench\WorkbenchPreferences;
use Tey\Kit\Features\SessionUi\Account\SecurityLog;
use Tey\Kit\Features\SessionUi\Dashboard\ActivityWidget;
use Tey\Kit\Features\SessionUi\Dashboard\PeopleWidget;
use Tey\Kit\Features\SessionUi\Dashboard\YouWidget;
use Tey\Kit\Features\SessionUi\Fortify\UpdatePassword;
use Tey\Kit\Features\SessionUi\Fortify\UpdateProfile;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Http\Middleware\RefreshTimezone;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;
use Tey\Kit\Features\SessionUi\Notifications\ReplaceConciergeInvitation;
use Tey\Kit\Features\SessionUi\Policies\UserAvatarPolicy;
use Tey\Kit\Features\SessionUi\Search\PeopleSearch;
use Tey\Kit\Features\SessionUi\Search\Searches;
use Tey\Kit\Features\SessionUi\ViewModels\ChallengeViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\ErrorViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\LoginViewModel;

final class SessionUiProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        // A user with exactly one reachable workspace has nothing to choose on the list: it opens that workspace at its first page.
        $registry->routes(__DIR__.'/routes/sections.php', 'web', $routes)
            ->navigation('dashboard.read', 'Dashboard', '', order: -10, active: '')
            ->abilities(['dashboard.read' => 'workspace.dashboard', 'members.manage' => 'workspace.settings', Searches::ABILITY => 'workspace.search'])
            // Global search is opt-in: workspace.search is off until `kit.capabilities` (or an operator, per workspace) switches it on.
            ->capabilities(['workspace.dashboard', 'workspace.search'])
            // The narrow token scope a connection asks for to read the dashboard over MCP (Dashboard\ReadDashboard), as settings.read is for settings.
            ->scopes(['dashboard.read' => 'Read the workspace dashboard', Searches::ABILITY => 'Search the workspace'])
            ->settingsSection('workspace', 'people', 'People', 'Who belongs to this workspace, their roles, and who is invited.', 'members.manage', 'Workspace', 0, 'users')
            ->settingsSection('personal', 'profile', 'Profile', 'Your name, email address and photo.', null, 'Account', -20, 'user')
            ->settingsSection('personal', 'password', 'Password', 'Change your password.', null, 'Account', -15, 'key-round')
            ->settingsSection('personal', 'security', 'Security', 'Two-factor authentication, signed-in devices and your security log.', null, 'Account', -10, 'shield-check');
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
        $registry->focusRoutes('invitations.*', 'account.switch', 'verification.*', 'focus.*');
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

    public function register(): void
    {
        // What has been declared as placeholder routes is remembered per application, never per PHP process.
        $this->app->singleton(PlaceholderRoutes::class);
        Defaults::apply($this->app, 'fortify', 'laravel/fortify');
        Defaults::apply($this->app, 'inertia', 'inertiajs/inertia-laravel');
    }

    public function boot(): void
    {
        // The kit's password policy (PasswordPolicy): 8 characters, not breached, no composition rules. A host's own Password::defaults() boots later and wins.
        Password::defaults(fn () => PasswordPolicy::default());
        $this->app->make(Router::class)->pushMiddlewareToGroup('web', HandleInertiaRequests::class);
        // The browser's zone, kept up to date until the person confirms theirs. `tz` is set by script in the browser, so it is read as it is: never encrypted, and validated on use.
        // Through the HTTP kernel, which owns the middleware groups (the router's own are rebuilt from it).
        $this->app->make(Kernel::class)->appendMiddlewareToGroup('web', RefreshTimezone::class);
        EncryptCookies::except([ClientTimezone::COOKIE]);
        Fortify::loginView(fn () => Inertia::render('Auth/Login/Page', new LoginViewModel('Sign in')));
        // A page's layout (section navigation, heading, document title, breadcrumbs) is built for the request in the shell.
        $this->app->bind(PageLayouts::class, SessionUiPageLayouts::class);
        // A Workbench's layout is remembered per person and workbench key (kit_user_state), saved through the kit's endpoint.
        $this->app->bind(WorkbenchPreferences::class, UserWorkbenchPreferences::class);
        Gate::policy(UserAvatar::class, UserAvatarPolicy::class);
        // The account pages use Fortify's actions: its profile and password updates are the kit's, and the guest pages it asks for are the kit's.
        $this->app->singleton(UpdatesUserProfileInformation::class, UpdateProfile::class);
        $this->app->singleton(UpdatesUserPasswords::class, UpdatePassword::class);
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactor/Page', new ChallengeViewModel('Two-factor verification')));
        Fortify::verifyEmailView(fn () => Inertia::render('Auth/VerifyEmail/Page', new ChallengeViewModel('Verify your email address', session('status'), request()->user()?->email)));
        SecurityLog::listen($this->app->make('events'));
        // The invitation email is the kit's branded one (the inviter's name and personal message, a specific subject and preheader), swapped in for Concierge's plain one.
        $this->app->make('events')->listen(NotificationSending::class, [ReplaceConciergeInvitation::class, 'handle']);
        // The link in an invitation (mailed, or shown to the person who invited): the page that explains it and lets the invited person in.
        if (class_exists(Concierge::class)) {
            $this->app->make(Concierge::class)->invitationUrlUsing(fn ($invitation, string $token): string => url('/invitations/'.$token));
        }
        Relation::morphMap(['kit-user-avatar' => UserAvatar::class]);
    }
}

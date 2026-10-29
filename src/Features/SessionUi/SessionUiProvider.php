<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
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
use Tey\Kit\Features\SessionUi\Fortify\PasswordConfirmed;
use Tey\Kit\Features\SessionUi\Fortify\ResetPassword;
use Tey\Kit\Features\SessionUi\Fortify\UpdatePassword;
use Tey\Kit\Features\SessionUi\Fortify\UpdateProfile;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Http\Middleware\RefreshTimezone;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;
use Tey\Kit\Features\SessionUi\Notifications\ReplaceConciergeInvitation;
use Tey\Kit\Features\SessionUi\Policies\UserAvatarPolicy;
use Tey\Kit\Features\SessionUi\ViewModels\ChallengeViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\ConfirmPasswordViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\LoginViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\ResetPasswordViewModel;

final class SessionUiProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        // The auth pages and the flows around them (invitations, the welcome steps, avatars, switching account). The workspace pages, the
        // account pages and the dashboard are workspace-ui's, so a host can adopt session-ui alone for its sign-in.
        $registry->routes(__DIR__.'/routes/session.php', 'web', $routes);
        $registry->focusRoutes('invitations.*', 'account.switch', 'verification.*', 'focus.*');
    }

    public function register(): void
    {
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
        // Without workspace-ui (an auth-only app) the kit's workspace list is not there to land on after sign-in: the kit's default is the app's own home,
        // `kit.home`. An app's own config/fortify.php decides for itself.
        if (! $this->app->make(Registry::class)->adopts('workspace-ui') && ! $this->app->configurationIsCached() && ! is_file($this->app->configPath('fortify.php'))) {
            config(['fortify.home' => (string) config('kit.home', '/')]);
        }
        // Likewise Concierge sends a person with no workspace to the no-workspace page, which is workspace-ui's: auth-only, it is the app's home.
        if (! $this->app->make(Registry::class)->adopts('workspace-ui') && ! $this->app->configurationIsCached() && ! is_file($this->app->configPath('concierge.php'))) {
            config(['concierge.workspaces.no_workspace_url' => (string) config('kit.home', '/')]);
        }
        Fortify::loginView(fn () => Inertia::render('Auth/Login/Page', new LoginViewModel('Sign in', session('status'))));
        // Password reset by emailed link (Fortify's resetPasswords): asking for the link, then choosing the new password under the kit's policy.
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Auth/ForgotPassword/Page', new ChallengeViewModel('Reset your password', session('status'))));
        Fortify::resetPasswordView(fn ($request) => Inertia::render('Auth/ResetPassword/Page', new ResetPasswordViewModel('Choose a new password', (string) $request->route('token'), (string) $request->query('email', ''))));
        // Password confirmation (Fortify's password.confirm): Cancel goes to the `cancel` query or the referer when it is on the app, else kit.home; confirming returns
        // to the intended URL only when it is on the app (SafeRedirect, in PasswordConfirmed).
        Fortify::confirmPasswordView(function ($request) {
            $home = (string) config('kit.home', '/');
            $referer = $request->headers->get('referer');
            $referer = is_string($referer) && parse_url($referer, PHP_URL_PATH) !== parse_url(route('password.confirm'), PHP_URL_PATH) ? $referer : null;
            $cancel = $request->query('cancel');

            return Inertia::render('Auth/ConfirmPassword/Page', new ConfirmPasswordViewModel('Confirm your password', SafeRedirect::sameOrigin(is_string($cancel) ? $cancel : $referer, $home)));
        });
        $this->app->singleton(PasswordConfirmedResponse::class, PasswordConfirmed::class);
        // A page's layout (section navigation, heading, document title, breadcrumbs) is built for the request in the shell.
        $this->app->bind(PageLayouts::class, SessionUiPageLayouts::class);
        // A Workbench's layout is remembered per person and workbench key (kit_user_state), saved through the kit's endpoint.
        $this->app->bind(WorkbenchPreferences::class, UserWorkbenchPreferences::class);
        Gate::policy(UserAvatar::class, UserAvatarPolicy::class);
        // The account pages use Fortify's actions: its profile and password updates are the kit's, and the guest pages it asks for are the kit's.
        $this->app->singleton(UpdatesUserProfileInformation::class, UpdateProfile::class);
        $this->app->singleton(UpdatesUserPasswords::class, UpdatePassword::class);
        $this->app->singleton(ResetsUserPasswords::class, ResetPassword::class);
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

<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Middleware as InertiaMiddleware;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Fortify\Fortify;
use Tey\Concierge\LocalAuth\LocalLogin;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Defaults;
use Tey\Kit\Features\Core\EnvironmentIndicator;
use Tey\Kit\Features\Core\KitShares;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\ViewModels\PageLayouts;
use Tey\Kit\Features\Core\Workbench\WorkbenchPreferences;
use Tey\Kit\Features\SessionUi\Account\SecurityLog;
use Tey\Kit\Features\SessionUi\Contracts\ConfirmPasswordCancelUrl;
use Tey\Kit\Features\SessionUi\Fortify\PasswordConfirmed;
use Tey\Kit\Features\SessionUi\Fortify\ResetPassword;
use Tey\Kit\Features\SessionUi\Fortify\UpdatePassword;
use Tey\Kit\Features\SessionUi\Fortify\UpdateProfile;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Http\Middleware\PrepareKitRequest;
use Tey\Kit\Features\SessionUi\Http\Middleware\RefreshTimezone;
use Tey\Kit\Features\SessionUi\Http\Middleware\StartKitRequest;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;
use Tey\Kit\Features\SessionUi\Policies\UserAvatarPolicy;
use Tey\Kit\Features\SessionUi\ViewModels\ChallengeViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\ConfirmPasswordViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\LoginViewModel;
use Tey\Kit\Features\SessionUi\ViewModels\ResetPasswordViewModel;
use Tey\Kit\Features\Shell\ShellSource;

final class SessionUiProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        // The auth pages and the flows around them (the welcome steps, avatars, switching account). The workspace pages, the account pages, the dashboard
        // and the invitation link are workspace-ui's, so a host can adopt session-ui with only core and shell for its sign-in.
        // `kit.routes.session-ui.<group> => false` switches one group off (SessionUiRoutes); an unknown key or a non-boolean stops the boot here.
        SessionUiRoutes::validate();
        $registry->routes(__DIR__.'/routes/session.php', 'web', $routes);
        $registry->focusRoutes('account.switch', 'verification.*', 'focus.*');
    }

    public function register(): void
    {
        Defaults::apply($this->app, 'fortify', 'laravel/fortify');
        Defaults::apply($this->app, 'inertia', 'inertiajs/inertia-laravel');
        // The shared `shell` prop (the shell feature's share) is built by the PageShell: the focus shell, avatars and, with workspaces, the switcher and navigation.
        $this->app->bind(ShellSource::class, PageShell::class);
        // Cancel on the confirm-password page: a host's own binding wins.
        $this->app->bindIf(ConfirmPasswordCancelUrl::class, ConfirmPasswordCancel::class);
    }

    /** @return ?array{url: string, email: string} Concierge's local sign-in offer, as the login page takes it; null when Concierge is not booted or offers none. */
    private static function localLogin(): ?array
    {
        if (! app()->bound(LocalLogin::class)) {
            return null;
        }
        $offer = app(LocalLogin::class)->offer();

        return $offer === null ? null : ['url' => $offer->url, 'email' => $offer->email];
    }

    public function boot(): void
    {
        // The kit's password policy (PasswordPolicy): 8 characters, not breached, no composition rules. A host's own Password::defaults() boots later and wins.
        Password::defaults(fn () => PasswordPolicy::default());
        // session-ui's props join the host's own HandleInertiaRequests through KitShares: shared here and put back by StartKitRequest on every web
        // request, so a flush (pest-plugin-browser's, before each request) can't drop them, and never over a key the host shares. Each is a closure
        // evaluated when a page renders, so nothing resolves at boot and no value outlives its request (under Octane too). `kitUi` and `kitToasts` are
        // core's (KitServiceProvider), `shell` is the shell feature's (ShellProvider, built by the bound ShellSource, the PageShell below): one producer each.
        KitShares::provide([
            'environment' => fn (): ?array => EnvironmentIndicator::current(),
            'kitPassword' => fn (): array => PasswordPolicy::client(),
            'passkeyOffer' => fn (): ?array => SessionUiRoutes::on('passkey-offer') ? PasskeyOffer::shared(request()->user('web')) : null,
            // The viewer's timezone for timed values (DateTime, the pickers): a calendar date never uses it.
            'kitTimezone' => fn (): string => ClientTimezone::forViewer(request()),
        ]);
        // Middleware joins `web` through the HTTP kernel, which owns the groups: the router's are rebuilt from it on every kernel change, so a push
        // to the router alone is dropped by the next appendMiddlewareToGroup. StartKitRequest runs first in `web` (first in the group and in the priority
        // list, so no host middleware sorts ahead of it): the request's memos begin and the kit's props are put back before the host's middleware,
        // route-model binding or an error page reads them. The registry's late hooks run once the session has started (PrepareKitRequest).
        $kernel = $this->app->make(Kernel::class);
        $kernel->prependMiddlewareToGroup('web', StartKitRequest::class);
        $kernel->prependToMiddlewarePriority(StartKitRequest::class);
        $kernel->appendMiddlewareToGroup('web', PrepareKitRequest::class);
        // Inertia still needs one middleware for the root view and asset version: the host's own, or, when its `web` group has none, the kit's plain one.
        $inertia = array_filter($kernel->getMiddlewareGroups()['web'] ?? [], fn (mixed $middleware): bool => is_string($middleware) && is_a(explode(':', $middleware)[0], InertiaMiddleware::class, true));
        if ($inertia === []) {
            $kernel->appendMiddlewareToGroup('web', HandleInertiaRequests::class);
        }
        // The browser's zone, kept up to date until the person confirms theirs. `tz` is set by script in the browser, so it is read as it is: never encrypted, and validated on use.
        $kernel->appendMiddlewareToGroup('web', RefreshTimezone::class);
        EncryptCookies::except([ClientTimezone::COOKIE]);
        // Without workspace-ui (an auth-only app) the kit's workspace list is not there to land on after sign-in: the kit's default is the app's own home,
        // `kit.home`. An app's own config/fortify.php decides for itself.
        if (! $this->app->make(Registry::class)->adopts('workspace-ui') && ! $this->app->configurationIsCached() && ! is_file($this->app->configPath('fortify.php'))) {
            config(['fortify.home' => (string) config('kit.home', '/')]);
        }
        // Likewise Concierge (with workspaces) sends a person with no workspace to the no-workspace page, which is workspace-ui's: auth-only, it is the app's home.
        if ($this->app->make(Registry::class)->adopts('workspaces') && ! $this->app->make(Registry::class)->adopts('workspace-ui') && ! $this->app->configurationIsCached() && ! is_file($this->app->configPath('concierge.php'))) {
            config(['concierge.workspaces.no_workspace_url' => (string) config('kit.home', '/')]);
        }
        // The one-click local sign-in is Concierge's to offer (LocalLogin decides the environment, the config and the route): null without Concierge.
        Fortify::loginView(fn () => Inertia::render('Auth/Login/Page', new LoginViewModel('Sign in', session('status'), self::localLogin())));
        // Password reset by emailed link (Fortify's resetPasswords): asking for the link, then choosing the new password under the kit's policy.
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Auth/ForgotPassword/Page', new ChallengeViewModel('Reset your password', session('status'))));
        Fortify::resetPasswordView(fn ($request) => Inertia::render('Auth/ResetPassword/Page', new ResetPasswordViewModel('Choose a new password', (string) $request->route('token'), (string) $request->query('email', ''))));
        // Password confirmation (Fortify's password.confirm): Cancel is the bound ConfirmPasswordCancelUrl's (the kit's ConfirmPasswordCancel unless the host binds
        // its own); confirming returns to the intended URL only when it is on the app (SafeRedirect, in PasswordConfirmed).
        Fortify::confirmPasswordView(fn ($request) => Inertia::render('Auth/ConfirmPassword/Page', new ConfirmPasswordViewModel('Confirm your password', $this->app->make(ConfirmPasswordCancelUrl::class)->resolve($request))));
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
        Relation::morphMap(['kit-user-avatar' => UserAvatar::class]);
    }
}

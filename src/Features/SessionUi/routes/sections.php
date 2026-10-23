<?php

use Illuminate\Support\Facades\Route;
use Glhd\Gretel\Registry;
use Glhd\Gretel\Resolvers\UrlResolver;
use Tey\Kit\Features\SessionUi\Http\AvatarController;
use Tey\Kit\Features\SessionUi\Http\AvatarImageController;
use Tey\Kit\Features\SessionUi\Http\DashboardController;
use Tey\Kit\Features\SessionUi\Http\DemoLoginController;
use Tey\Kit\Features\SessionUi\Http\InvitationAccountController;
use Tey\Kit\Features\SessionUi\Http\NoWorkspaceController;
use Tey\Kit\Features\SessionUi\Http\InvitationController;
use Tey\Kit\Features\SessionUi\Http\HomeController;
use Tey\Kit\Features\SessionUi\Http\MemberController;
use Tey\Kit\Features\SessionUi\Http\OwnershipController;
use Tey\Kit\Features\SessionUi\Http\PeopleController;
use Tey\Kit\Features\SessionUi\Http\PeopleModalController;
use Tey\Kit\Features\SessionUi\Http\PeopleInvitationController;
use Tey\Kit\Features\SessionUi\Http\SwitchAccountController;
use Tey\Kit\Features\SessionUi\Http\WelcomeCardController;
use Tey\Kit\Features\SessionUi\Http\WelcomeController;
use Tey\Kit\Features\SessionUi\Http\WelcomeSkipController;
use Tey\Kit\Features\SessionUi\Http\PasswordController;
use Tey\Kit\Features\SessionUi\Http\PersonalSectionsController;
use Tey\Kit\Features\SessionUi\Http\ProfileController;
use Tey\Kit\Features\SessionUi\Http\RecoveryCodesController;
use Tey\Kit\Features\SessionUi\Http\SecurityController;
use Tey\Kit\Features\SessionUi\Http\SessionsController;
use Tey\Kit\Features\SessionUi\Http\TwoFactorController;
use Tey\Kit\Features\SessionUi\Http\WorkspaceSectionsController;
use Tey\Kit\Features\Workspaces\Slugs;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

// Every trail is rooted at "/" (the home icon, named by the workspace or the product). The Dashboard IS the workspace's root: `/@{workspace}`,
// or "/" in the short form (the home route serves it there). The settings landing page and /me are its siblings.
$home = ['session-ui', 'home'];
Route::controller(HomeController::class)->group(function (): void {
    $route = WorkspaceRoutes::personalPage(Route::get('/', '__invoke')->name('home'), static fn (): string => WorkspaceUrl::homeName());
    // The home crumb opens the current workspace's dashboard (its root), else the workspace list.
    if (($crumb = app(Registry::class)->get($route)) !== null) {
        $crumb->url = new UrlResolver(static fn (): string => ($workspace = WorkspaceUrl::currentId()) !== null ? WorkspaceUrl::to($workspace) : '/me/workspaces');
    }
});
// The old Dashboard address moved to the workspace's root, for good.
Route::permanentRedirect('@{workspace}/dashboard', '/@{workspace}')->where(['workspace' => Slugs::PATTERN]);
Route::permanentRedirect('dashboard', '/');
WorkspaceRoutes::define(function (bool $full) use ($home): void {
    // The full form only: the short form's root is "/", the home route, which serves the dashboard for a user with one workspace.
    if ($full) {
        Route::controller(DashboardController::class)->group(function () use ($full, $home): void {
            WorkspaceRoutes::page(Route::get('', 'show'), $full, 'session-ui', 'workspaces.dashboard.index', 'Dashboard', $home);
        });
    }
    Route::controller(WorkspaceSectionsController::class)->prefix('settings')->group(function () use ($full, $home): void {
        WorkspaceRoutes::page(Route::get('', 'index'), $full, 'session-ui', 'workspaces.settings.index', 'Settings', $home);
    });
    // Settings › General › Transfer ownership (the owner only, the password asked for again).
    Route::controller(OwnershipController::class)->prefix('settings/ownership')->group(function (): void {
        Route::put('', 'update')->middleware('throttle:5,1');
    });
    // Settings › People: the member list (server-driven), and the members' and invitations' own routes.
    Route::prefix('settings/people')->group(function () use ($full): void {
        Route::controller(PeopleController::class)->group(function () use ($full): void {
            WorkspaceRoutes::page(Route::get('', 'index'), $full, 'session-ui', 'workspaces.people.index', 'People', ['session-ui', 'workspaces.settings.index']);
        });
        Route::controller(MemberController::class)->prefix('members/{membership}')->whereNumber('membership')->group(function (): void {
            // The Change role modal (GET) and the change it sends (PUT) share the URL.
            Route::controller(PeopleModalController::class)->group(fn () => Route::get('', 'edit'));
            Route::put('', 'update');
            Route::delete('', 'destroy');
        });
        Route::controller(PeopleInvitationController::class)->prefix('invitations')->group(function (): void {
            // The Invite member modal (GET) and the invitation it sends (POST) share the URL.
            Route::controller(PeopleModalController::class)->group(fn () => Route::get('', 'create'));
            Route::post('', 'store');
            Route::put('{invitation}', 'update')->where('invitation', '[0-9A-Za-z]{26}');
            Route::delete('{invitation}', 'destroy')->where('invitation', '[0-9A-Za-z]{26}');
        });
    });
});
Route::middleware(['auth:web', 'concierge.active'])->controller(PersonalSectionsController::class)->prefix('me')->group(function () use ($home): void {
    WorkspaceRoutes::personalPage(Route::get('', 'index')->name('me.index'), 'Account', $home);
});
Route::middleware(['auth:web', 'concierge.active'])->group(function () use ($home): void {
    Route::controller(ProfileController::class)->prefix('me/profile')->group(function () use ($home): void {
        WorkspaceRoutes::personalPage(Route::get('', 'show')->name('me.profile'), 'Profile', ['session-ui', 'me.index']);
    });
    Route::controller(PasswordController::class)->prefix('me/password')->group(function () use ($home): void {
        WorkspaceRoutes::personalPage(Route::get('', 'show')->name('me.password'), 'Password', ['session-ui', 'me.index']);
    });
    // Security: two-factor authentication through Fortify's actions, recovery codes, and the other sessions. The password is asked for again.
    Route::prefix('me/security')->group(function () use ($home): void {
        Route::controller(SecurityController::class)->group(function () use ($home): void {
            WorkspaceRoutes::personalPage(Route::get('', 'show')->name('me.security'), 'Security', ['session-ui', 'me.index']);
        });
        Route::controller(TwoFactorController::class)->prefix('two-factor')->middleware('throttle:10,1')->group(function (): void {
            Route::post('', 'store');
            Route::put('', 'update');
            Route::delete('', 'destroy');
        });
        Route::controller(RecoveryCodesController::class)->prefix('recovery-codes')->middleware('throttle:10,1')->group(function (): void {
            Route::post('', 'store');
        });
        Route::controller(SessionsController::class)->prefix('sessions')->middleware('throttle:10,1')->group(function (): void {
            Route::delete('', 'destroy');
        });
    });
    // Your own avatar: no id in the URL, it is always the signed-in user's.
    Route::controller(AvatarController::class)->prefix('me/avatar')->group(function (): void {
        Route::post('', 'store')->name('me.avatar.store');
        Route::delete('', 'destroy')->name('me.avatar.destroy');
    });
});
// An avatar's image: public, by its random token, with no session or cookie so it can be cached by anything.
Route::controller(AvatarImageController::class)->prefix('avatars')->group(function (): void {
    Route::get('{token}/{size?}', 'show')->where(['token' => '[A-Za-z0-9]{40}', 'size' => '2x'])->name('avatars.show')
        ->withoutMiddleware([\Illuminate\Cookie\Middleware\EncryptCookies::class, \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class, \Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class, \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests::class]);
});
// Planned pages, registered with `Registry::placeholder()` or a settings section's `placeholder: true`: at the real page's name and address, until a controller replaces them.
app(\Tey\Kit\Features\SessionUi\PlaceholderRoutes::class)->register();
// The first-run demo's one-click sign-in: signed, expiring and local only (a 404 anywhere else; Concierge's own environment check decides).
Route::get('demo-login/{user}', DemoLoginController::class)->where('user', '[0-9]+')->middleware('signed')->name('demo.login');
// Where a signed-in person with no workspace lands (a new account, or after leaving or being removed): FocusShell, never a 403.
WorkspaceRoutes::personalPage(Route::get('no-workspace', NoWorkspaceController::class)->middleware('auth:web')->name('focus.no-workspace'), 'No workspace yet', $home);
// Switch account: signs this session out and returns to sign in (the FocusShell's account cluster).
Route::post('switch-account', SwitchAccountController::class)->name('account.switch')->middleware('auth:web');
Route::controller(WelcomeCardController::class)->middleware('auth:web')->group(function (): void {
    Route::delete('me/welcome/{workspace}', 'destroy')->where('workspace', '[0-9]+')->name('me.welcome.dismiss');
});
// The welcome steps after joining (FocusShell): the next pending step, one step, saving it, skipping it. None blocks.
Route::controller(WelcomeController::class)->middleware('auth:web')->prefix('welcome')->group(function () use ($home): void {
    WorkspaceRoutes::personalPage(Route::get('', 'index')->name('focus.welcome'), 'Welcome', $home);
    WorkspaceRoutes::personalPage(Route::get('{step}', 'show')->where('step', '[a-z0-9-]+')->name('focus.welcome.show'), 'Welcome', $home);
    Route::put('{step}', 'update')->where('step', '[a-z0-9-]+')->name('focus.welcome.update');
    Route::post('{step}', 'store')->where('step', '[a-z0-9-]+')->name('focus.welcome.store');
});
Route::post('welcome/{step}/skip', WelcomeSkipController::class)->where('step', '[a-z0-9-]+')->middleware('auth:web')->name('focus.welcome.skip');
// A workspace invitation link: public (a guest follows it), accepted by a signed-in user, with the account it is for created from the same page.
Route::prefix('invitations/{token}')->where(['token' => '[A-Za-z0-9_-]{20,100}'])->group(function () use ($home): void {
    Route::controller(InvitationController::class)->group(function () use ($home): void {
        WorkspaceRoutes::personalPage(Route::get('', 'show')->middleware('throttle:30,1')->name('invitations.show'), 'Invitation', $home);
        Route::post('', 'store')->middleware(['auth:web', 'throttle:10,1']);
    });
    Route::controller(InvitationAccountController::class)->group(function (): void {
        Route::post('account', 'store')->middleware('throttle:10,1');
    });
});

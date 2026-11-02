<?php

use Glhd\Gretel\Registry;
use Glhd\Gretel\Resolvers\UrlResolver;
use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Core\HomeRoute;
use Tey\Kit\Features\SessionUi\Http\AccountDeletionController;
use Tey\Kit\Features\SessionUi\Http\DashboardController;
use Tey\Kit\Features\SessionUi\Http\DemoLoginController;
use Tey\Kit\Features\SessionUi\Http\HomeController;
use Tey\Kit\Features\SessionUi\Http\InvitationAccountController;
use Tey\Kit\Features\SessionUi\Http\InvitationController;
use Tey\Kit\Features\SessionUi\Http\MemberController;
use Tey\Kit\Features\SessionUi\Http\NoWorkspaceController;
use Tey\Kit\Features\SessionUi\Http\OwnershipController;
use Tey\Kit\Features\SessionUi\Http\PasswordController;
use Tey\Kit\Features\SessionUi\Http\PeopleController;
use Tey\Kit\Features\SessionUi\Http\PeopleInvitationController;
use Tey\Kit\Features\SessionUi\Http\PeopleModalController;
use Tey\Kit\Features\SessionUi\Http\PersonalSectionsController;
use Tey\Kit\Features\SessionUi\Http\ProfileController;
use Tey\Kit\Features\SessionUi\Http\RecoveryCodesController;
use Tey\Kit\Features\SessionUi\Http\SearchController;
use Tey\Kit\Features\SessionUi\Http\SecurityController;
use Tey\Kit\Features\SessionUi\Http\SessionsController;
use Tey\Kit\Features\SessionUi\Http\TransferOwnershipModalController;
use Tey\Kit\Features\SessionUi\Http\TwoFactorController;
use Tey\Kit\Features\SessionUi\Http\WelcomeCardController;
use Tey\Kit\Features\SessionUi\Http\WorkspaceDeletionController;
use Tey\Kit\Features\SessionUi\Http\WorkspaceSectionsController;
use Tey\Kit\Features\SessionUi\PlaceholderRoutes;
use Tey\Kit\Features\SessionUi\SessionUiRoutes;
use Tey\Kit\Features\Workspaces\Slugs;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

// workspace-ui's pages: the kit's home, the dashboard, search, the settings and account landing pages, People, the account pages and the no-workspace page.
// They keep session-ui's route names and `kit.routes.session-ui` options, which named them before the split.
// Every trail is rooted at "/" (the home icon, named by the workspace or the product). The Dashboard IS the workspace's root: `/@{workspace}`,
// or "/" in the short form (the home route serves it there). The settings landing page and /me are its siblings.
$home = HomeRoute::parent();
// `kit.home` is where the kit's own home is: "/" by default; a host with its own landing page at "/" moves the kit's home (and the trails' root) elsewhere, such as "/app".
Route::controller(HomeController::class)->group(function (): void {
    $route = WorkspaceRoutes::personalPage(Route::get((string) config('kit.home', '/'), '__invoke')->name(HomeRoute::name()), static fn (): string => WorkspaceUrl::homeName());
    // The home crumb opens the current workspace's dashboard (its root), else the workspace list.
    if (($crumb = app(Registry::class)->get($route)) !== null) {
        $crumb->url = new UrlResolver(static fn (): string => ($workspace = WorkspaceUrl::currentMemberId()) !== null ? WorkspaceUrl::to($workspace) : '/me/workspaces');
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
    // Global search: the results page, and the JSON the header palette asks for. Absent (404) while workspace.search is off; limited per person (Searches::throttle).
    Route::controller(SearchController::class)->prefix('search')->group(function () use ($full, $home): void {
        WorkspaceRoutes::page(Route::get('', 'index'), $full, 'session-ui', 'workspaces.search.index', 'Search', $home);
    });
    Route::controller(WorkspaceSectionsController::class)->prefix('settings')->group(function () use ($full, $home): void {
        WorkspaceRoutes::page(Route::get('', 'index'), $full, 'session-ui', 'workspaces.settings.index', 'Settings', $home);
    });
    // Transfer ownership (the owner only, the password asked for again and the workspace's name typed), sent from the Transfer ownership modal over People.
    Route::controller(OwnershipController::class)->prefix('settings/ownership')->group(function (): void {
        Route::put('', 'update')->middleware('throttle:5,1');
    });
    // Settings › Delete workspace (the owner only, the workspace's name typed). WorkspaceDeleted is dispatched after the commit.
    Route::controller(WorkspaceDeletionController::class)->prefix('settings/delete')->group(function () use ($full): void {
        WorkspaceRoutes::page(Route::get('', 'show'), $full, 'session-ui', 'workspaces.settings.delete', 'Delete workspace', ['session-ui', 'workspaces.settings.index']);
        Route::delete('', 'destroy')->middleware('throttle:5,1');
    });
    // Settings › People: the member list (server-driven), and the members' and invitations' own routes.
    Route::prefix('settings/people')->group(function () use ($full): void {
        Route::controller(PeopleController::class)->group(function () use ($full): void {
            WorkspaceRoutes::page(Route::get('', 'index'), $full, 'session-ui', 'workspaces.people.index', 'People', ['session-ui', 'workspaces.settings.index']);
        });
        Route::controller(MemberController::class)->prefix('members/{membership}')->whereNumber('membership')->group(function (): void {
            // The Change role modal (GET) and the change it sends (PUT) share the URL.
            Route::controller(PeopleModalController::class)->group(fn () => Route::get('', 'edit'));
            // The Transfer ownership modal (the owner only); the transfer itself goes to settings/ownership.
            Route::controller(TransferOwnershipModalController::class)->group(fn () => Route::get('transfer', 'edit'));
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
Route::middleware(['auth:web', 'concierge.active'])->group(function (): void {
    Route::controller(ProfileController::class)->prefix('me/profile')->group(function (): void {
        WorkspaceRoutes::personalPage(Route::get('', 'show')->name('me.profile'), 'Profile', ['session-ui', 'me.index']);
    });
    Route::controller(PasswordController::class)->prefix('me/password')->group(function (): void {
        WorkspaceRoutes::personalPage(Route::get('', 'show')->name('me.password'), 'Password', ['session-ui', 'me.index']);
    });
    // Delete account: the current password, refused while the person owns a workspace. AccountDeleted is dispatched after the commit.
    Route::controller(AccountDeletionController::class)->prefix('me/delete')->group(function (): void {
        WorkspaceRoutes::personalPage(Route::get('', 'show')->name('me.delete'), 'Delete account', ['session-ui', 'me.index']);
        Route::delete('', 'destroy')->middleware('throttle:5,1');
    });
    // Security: two-factor authentication through Fortify's actions, recovery codes, and the other sessions. The password is asked for again.
    Route::prefix('me/security')->group(function (): void {
        Route::controller(SecurityController::class)->group(function (): void {
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
});
// Planned pages, registered with `Registry::placeholder()` or a settings section's `placeholder: true`: at the real page's name and address, until a controller replaces them.
app(PlaceholderRoutes::class)->register();
// Where a signed-in person with no workspace lands (a new account, or after leaving or being removed): FocusShell, never a 403.
WorkspaceRoutes::personalPage(Route::get('no-workspace', NoWorkspaceController::class)->middleware('auth:web')->name('focus.no-workspace'), 'No workspace yet', $home);
// The dashboard's welcome card's dismiss. Group `welcome` (SessionUiRoutes), with the welcome steps.
if (SessionUiRoutes::on('welcome')) {
    Route::controller(WelcomeCardController::class)->middleware('auth:web')->group(function (): void {
        Route::delete('me/welcome/{workspace}', 'destroy')->where('workspace', '[0-9]+')->name('me.welcome.dismiss');
    });
}
// The first-run demo's one-click sign-in: signed, expiring and local only (a 404 anywhere else; Concierge's own environment check decides). Group `demo-login`.
if (SessionUiRoutes::on('demo-login')) {
    Route::get('demo-login/{user}', DemoLoginController::class)->where('user', '[0-9]+')->middleware('signed')->name('demo.login');
}
// A workspace invitation link (session-ui's before 0.6.0, keeping its name): public (a guest follows it), accepted by a signed-in user, with the account it is for created from the same page.
// Group `invitations` (SessionUiRoutes): a host with its own invitation flow turns it off, transitionally, and its own `invitations/{token}` and names serve the links.
if (SessionUiRoutes::on('invitations')) {
    Route::prefix('invitations/{token}')->where(['token' => '[A-Za-z0-9_-]{20,100}'])->group(function () use ($home): void {
        Route::controller(InvitationController::class)->group(function () use ($home): void {
            WorkspaceRoutes::personalPage(Route::get('', 'show')->middleware('throttle:30,1')->name('invitations.show'), 'Invitation', $home);
            Route::post('', 'store')->middleware(['auth:web', 'throttle:10,1']);
        });
        Route::controller(InvitationAccountController::class)->group(function (): void {
            Route::post('account', 'store')->middleware('throttle:10,1');
        });
    });
}

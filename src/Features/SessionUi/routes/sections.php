<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\SessionUi\Http\AvatarController;
use Tey\Kit\Features\SessionUi\Http\AvatarImageController;
use Tey\Kit\Features\SessionUi\Http\DashboardController;
use Tey\Kit\Features\SessionUi\Http\InvitationAccountController;
use Tey\Kit\Features\SessionUi\Http\InvitationController;
use Tey\Kit\Features\SessionUi\Http\HomeController;
use Tey\Kit\Features\SessionUi\Http\MemberController;
use Tey\Kit\Features\SessionUi\Http\OwnershipController;
use Tey\Kit\Features\SessionUi\Http\PeopleController;
use Tey\Kit\Features\SessionUi\Http\PeopleInvitationController;
use Tey\Kit\Features\SessionUi\Http\PersonalSectionsController;
use Tey\Kit\Features\SessionUi\Http\ProfileController;
use Tey\Kit\Features\SessionUi\Http\WorkspaceSectionsController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

// Every trail is rooted at "/" (the home icon, named by the workspace or the product). The Dashboard, the settings landing page and /me
// are its siblings.
$home = ['session-ui', 'home'];
Route::controller(HomeController::class)->group(function (): void {
    WorkspaceRoutes::personalPage(Route::get('/', '__invoke')->name('home'), static fn (): string => WorkspaceUrl::homeName());
});
WorkspaceRoutes::define(function (bool $full) use ($home): void {
    Route::controller(DashboardController::class)->prefix('dashboard')->group(function () use ($full, $home): void {
        WorkspaceRoutes::page(Route::get('', 'show'), $full, 'session-ui', 'workspaces.dashboard.index', 'Dashboard', $home);
    });
    Route::controller(WorkspaceSectionsController::class)->prefix('settings')->group(function () use ($full, $home): void {
        WorkspaceRoutes::page(Route::get('', 'index'), $full, 'session-ui', 'workspaces.settings.index', 'Settings', $home);
    });
    // Settings › People: the member list (server-driven), and the members' and invitations' own routes.
    Route::prefix('settings/people')->group(function () use ($full): void {
        Route::controller(PeopleController::class)->group(function () use ($full): void {
            WorkspaceRoutes::page(Route::get('', 'index'), $full, 'session-ui', 'workspaces.people.index', 'People', ['session-ui', 'workspaces.settings.index']);
        });
        Route::controller(MemberController::class)->prefix('members/{membership}')->whereNumber('membership')->group(function (): void {
            Route::put('', 'update');
            Route::delete('', 'destroy');
        });
        Route::controller(OwnershipController::class)->prefix('ownership')->group(function (): void {
            Route::put('', 'update')->middleware('throttle:5,1');
        });
        Route::controller(PeopleInvitationController::class)->prefix('invitations')->group(function (): void {
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

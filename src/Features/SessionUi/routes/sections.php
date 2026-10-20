<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\SessionUi\Http\AvatarController;
use Tey\Kit\Features\SessionUi\Http\DashboardController;
use Tey\Kit\Features\SessionUi\Http\HomeController;
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

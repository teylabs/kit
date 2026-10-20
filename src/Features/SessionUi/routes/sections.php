<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\SessionUi\Http\DashboardController;
use Tey\Kit\Features\SessionUi\Http\HomeController;
use Tey\Kit\Features\SessionUi\Http\SectionsController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

// Every trail is rooted at "/" (the home icon, named by the workspace or the product). The Dashboard, the settings landing page and /me
// are its siblings.
$home = ['session-ui', 'home'];
WorkspaceRoutes::personalPage(Route::get('/', HomeController::class)->name('home'), static fn (): string => WorkspaceUrl::homeName());
WorkspaceRoutes::define(function (bool $full) use ($home): void {
    WorkspaceRoutes::page(Route::get('dashboard', [DashboardController::class, 'show']), $full, 'session-ui', 'workspaces.dashboard.index', 'Dashboard', $home);
    WorkspaceRoutes::page(Route::get('settings', [SectionsController::class, 'workspace']), $full, 'session-ui', 'workspaces.settings.index', 'Settings', $home);
});
Route::middleware(['auth:web', 'concierge.active'])->group(function () use ($home): void {
    WorkspaceRoutes::personalPage(Route::get('/me', [SectionsController::class, 'personal'])->name('me.index'), 'Account', $home);
});

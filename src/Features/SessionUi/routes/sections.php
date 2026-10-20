<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\SessionUi\Http\SectionsController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

// A workspace's settings landing page, in both URL forms, and the user's own at /me. Sections are pages under them.
WorkspaceRoutes::define(function (bool $full): void {
    WorkspaceRoutes::page(Route::get('settings', [SectionsController::class, 'workspace']), $full, 'session-ui', 'workspaces.settings.index', 'Settings');
});
Route::middleware(['auth:web', 'concierge.active'])->group(function (): void {
    WorkspaceRoutes::personalPage(Route::get('/me', [SectionsController::class, 'personal'])->name('me.index'), 'Account');
});

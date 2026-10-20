<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Workspaces\Http\WorkspaceController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

Route::middleware(['auth:web', 'concierge.active'])->group(function (): void {
    // The chooser is a personal, cross-workspace page: it lives under `/me`.
    WorkspaceRoutes::personalPage(Route::get('/me/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index'), 'Workspaces');
    // The old chooser and `/workspaces/{id or slug}/...` pages: permanently moved to `/me/workspaces` and `/@{slug}/...`.
    Route::get('/workspaces', [WorkspaceController::class, 'movedIndex']);
    Route::get('/workspaces/{workspace}/{path?}', [WorkspaceController::class, 'moved'])->where('path', '.*');
    Route::post('/tenant-invitations/accept', [WorkspaceController::class, 'accept']);
});

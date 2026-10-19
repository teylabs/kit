<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Workspaces\Http\WorkspaceController;

Route::middleware(['auth:web', 'concierge.active'])->group(function (): void {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/tenant-invitations/accept', [WorkspaceController::class, 'accept']);
});

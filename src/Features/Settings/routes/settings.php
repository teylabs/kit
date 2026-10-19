<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Settings\Http\SettingsController;

Route::middleware(['auth:web', 'concierge.active'])->group(function (): void {
    Route::get('/workspaces/{workspace}/settings', [SettingsController::class, 'show'])->whereNumber('workspace');
    Route::put('/workspaces/{workspace}/settings', [SettingsController::class, 'update'])->whereNumber('workspace');
});

<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Settings\Http\SettingsController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

WorkspaceRoutes::define(function (bool $full): void {
    $show = Route::get('settings', [SettingsController::class, 'show']);
    if ($full) {
        $show->name('workspaces.settings.index');
    }
    Route::put('settings', [SettingsController::class, 'update']);
});

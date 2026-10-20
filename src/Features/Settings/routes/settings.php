<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Settings\Http\SettingsController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

WorkspaceRoutes::define(function (bool $full): void {
    WorkspaceRoutes::page(Route::get('settings', [SettingsController::class, 'show']), $full, 'settings', 'workspaces.settings.index', 'Settings');
    Route::put('settings', [SettingsController::class, 'update']);
});

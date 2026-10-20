<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Settings\Http\SettingsController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

// The General section of a workspace's settings; the landing page that lists the sections is session-ui's.
WorkspaceRoutes::define(function (bool $full): void {
    WorkspaceRoutes::page(Route::get('settings/general', [SettingsController::class, 'show']), $full, 'settings', 'workspaces.settings.general', 'General', ['session-ui', 'workspaces.settings.index']);
    Route::put('settings/general', [SettingsController::class, 'update']);
});

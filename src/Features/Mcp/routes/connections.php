<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Mcp\Http\ConnectionController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

// The Connections section of a workspace's settings.
WorkspaceRoutes::define(function (bool $full): void {
    Route::controller(ConnectionController::class)->prefix('settings/connections')->group(function () use ($full): void {
        Route::post('', 'store');
        WorkspaceRoutes::page(Route::get('', 'index'), $full, 'mcp', 'workspaces.connections.index', 'AI assistants', ['session-ui', 'workspaces.settings.index']);
        Route::delete('{connection}', 'destroy')->whereNumber('connection');
    });
});

<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Mcp\Http\ConnectionController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

// The Connections section of a workspace's settings.
WorkspaceRoutes::define(function (bool $full): void {
    Route::post('settings/connections', [ConnectionController::class, 'store']);
    WorkspaceRoutes::page(Route::get('settings/connections', [ConnectionController::class, 'index']), $full, 'mcp', 'workspaces.connections.index', 'Connections', ['session-ui', 'workspaces.settings.index']);
    Route::delete('settings/connections/{connection}', [ConnectionController::class, 'destroy'])->whereNumber('connection');
});

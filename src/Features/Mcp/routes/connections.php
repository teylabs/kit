<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Mcp\Http\ConnectionController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

WorkspaceRoutes::define(function (bool $full): void {
    Route::post('connections', [ConnectionController::class, 'store']);
    WorkspaceRoutes::page(Route::get('connections', [ConnectionController::class, 'index']), $full, 'mcp', 'workspaces.connections.index', 'Connections');
    Route::delete('connections/{connection}', [ConnectionController::class, 'destroy'])->whereNumber('connection');
});

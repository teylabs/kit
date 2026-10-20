<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Mcp\Http\ConnectionController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

WorkspaceRoutes::define(function (bool $full): void {
    Route::post('connections', [ConnectionController::class, 'store']);
    $index = Route::get('connections', [ConnectionController::class, 'index']);
    if ($full) {
        $index->name('workspaces.connections.index');
    }
    Route::delete('connections/{connection}', [ConnectionController::class, 'destroy'])->whereNumber('connection');
});

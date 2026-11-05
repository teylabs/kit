<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Finalize\Http\FinalizeController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

// A flow's gate for one subject, and its writes. The page that shows the gate is the app's; these answer JSON, and send a browser back.
WorkspaceRoutes::define(function (bool $full): void {
    Route::controller(FinalizeController::class)->prefix('finalize/{flow}/{subject}')->where(['flow' => '[a-z0-9._-]+', 'subject' => '[^/]+'])->group(function () use ($full): void {
        Route::get('', 'show')->name($full ? 'finalize.show' : 'short.finalize.show');
        Route::post('steps/{step}/confirm', 'confirm')->where('step', '[a-z0-9._-]+')->name($full ? 'finalize.confirm' : 'short.finalize.confirm');
        Route::post('finalize', 'finalize')->name($full ? 'finalize.commit' : 'short.finalize.commit');
        Route::post('unfinalize', 'unfinalize')->name($full ? 'finalize.unfinalize' : 'short.finalize.unfinalize');
    });
});

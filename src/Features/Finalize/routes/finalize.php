<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Finalize\Http\ConfirmationController;
use Tey\Kit\Features\Finalize\Http\FinalizationController;
use Tey\Kit\Features\Finalize\Http\FinalizeController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

// A flow's gate for one subject, and its writes. The page that shows the gate is the app's; these answer JSON, and send a browser back.
WorkspaceRoutes::define(function (bool $full): void {
    $at = ['flow' => '[a-z0-9._-]+', 'subject' => '[^/]+'];
    Route::controller(FinalizeController::class)->prefix('finalize/{flow}/{subject}')->where($at)->group(function () use ($full): void {
        Route::get('', 'show')->name($full ? 'finalize.show' : 'short.finalize.show');
    });
    Route::controller(ConfirmationController::class)->prefix('finalize/{flow}/{subject}')->where($at)->group(function () use ($full): void {
        Route::post('steps/{step}/confirm', 'store')->where('step', '[a-z0-9._-]+')->name($full ? 'finalize.confirm' : 'short.finalize.confirm');
    });
    Route::controller(FinalizationController::class)->prefix('finalize/{flow}/{subject}')->where($at)->group(function () use ($full): void {
        Route::post('finalize', 'store')->name($full ? 'finalize.commit' : 'short.finalize.commit');
        Route::post('unfinalize', 'destroy')->name($full ? 'finalize.unfinalize' : 'short.finalize.unfinalize');
    });
});

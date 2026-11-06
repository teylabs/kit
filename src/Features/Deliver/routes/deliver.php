<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Deliver\Http\AttachmentController;
use Tey\Kit\Features\Deliver\Http\CancellationController;
use Tey\Kit\Features\Deliver\Http\DeliverController;
use Tey\Kit\Features\Deliver\Http\DeliveryController;
use Tey\Kit\Features\Deliver\Http\PreviewController;
use Tey\Kit\Features\Deliver\Http\ResendController;
use Tey\Kit\Features\Deliver\Http\RevocationController;
use Tey\Kit\Features\Deliver\Http\TestDeliveryController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

// A definition's deliver state for one subject, its preview and document, and its writes. The page is the app's; these answer JSON, and send a browser back.
WorkspaceRoutes::define(function (bool $full): void {
    $at = ['definition' => '[a-z0-9._-]+', 'subject' => '[^/]+'];
    $name = fn (string $route): string => ($full ? '' : 'short.').'deliver.'.$route;
    Route::controller(DeliverController::class)->prefix('deliver/{definition}/{subject}')->where($at)->group(function () use ($name): void {
        Route::get('', 'show')->name($name('show'));
    });
    Route::controller(PreviewController::class)->prefix('deliver/{definition}/{subject}')->where($at)->group(function () use ($name): void {
        Route::post('preview', 'store')->name($name('preview'));
    });
    Route::controller(AttachmentController::class)->prefix('deliver/{definition}/{subject}')->where($at)->group(function () use ($name): void {
        Route::get('attachment', 'show')->name($name('attachment'));
    });
    Route::controller(DeliveryController::class)->prefix('deliver/{definition}/{subject}')->where($at)->group(function () use ($name): void {
        Route::post('send', 'store')->name($name('send'));
    });
    Route::controller(TestDeliveryController::class)->prefix('deliver/{definition}/{subject}')->where($at)->group(function () use ($name): void {
        Route::post('test', 'store')->name($name('test'));
    });
    Route::controller(CancellationController::class)->prefix('deliver/{definition}/{subject}/deliveries/{delivery}')->where([...$at, 'delivery' => '[0-9]+'])->group(function () use ($name): void {
        Route::post('cancel', 'store')->name($name('cancel'));
    });
    Route::controller(ResendController::class)->prefix('deliver/{definition}/{subject}/deliveries/{delivery}')->where([...$at, 'delivery' => '[0-9]+'])->group(function () use ($name): void {
        Route::post('resend', 'store')->name($name('resend'));
    });
    Route::controller(RevocationController::class)->prefix('deliver/{definition}/{subject}/deliveries/{delivery}')->where([...$at, 'delivery' => '[0-9]+'])->group(function () use ($name): void {
        Route::post('revoke', 'store')->name($name('revoke'));
    });
});

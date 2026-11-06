<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Deliver\Http\CustomerLinkController;

// The customer's link: signed, with a per-delivery token stored only as a hash. Outside any workspace route: the delivery names its own workspace.
Route::controller(CustomerLinkController::class)->group(function (): void {
    Route::get('d/{delivery}/{token}', 'show')->where(['delivery' => '[0-9]+', 'token' => '[a-f0-9]{64}'])->name('kit.deliver.view');
});

<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::get('/orders', fn () => Inertia::render('Orders/Index'));
    Route::delete('/orders/{order}', fn () => back())->name('orders.destroy');
});

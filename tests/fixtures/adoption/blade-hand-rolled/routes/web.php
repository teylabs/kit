<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => view('auth.login'))->name('login');

Route::middleware('auth')->group(function () {
    Route::get('/orders', fn () => view('orders.index'))->name('orders.index');
    Route::post('/orders', fn () => back()->with('status', 'order-created'))->name('orders.store');
    Route::delete('/orders/{order}', fn () => back())->name('orders.destroy');
    Route::post('/logout', fn () => redirect('/'))->name('logout');
});

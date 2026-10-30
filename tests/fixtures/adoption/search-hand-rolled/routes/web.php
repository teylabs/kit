<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::get('/search', fn () => Inertia::render('Orders/Index'))->name('search');
});

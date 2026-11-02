<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Marketing/Home'));
Route::get('/login', fn () => Inertia::render('Login'))->name('login');

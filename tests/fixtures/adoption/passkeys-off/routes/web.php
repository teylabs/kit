<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/login', 'Auth/Login/Page')->middleware('guest')->name('login');

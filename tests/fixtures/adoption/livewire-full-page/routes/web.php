<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/settings/profile', Profile::class)->name('settings.profile');
    Route::get('/settings/appearance', Appearance::class)->name('settings.appearance');
    Route::post('/logout', fn () => redirect('/'))->name('logout');
});

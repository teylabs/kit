<?php

use Illuminate\Support\Facades\Route;

Route::prefix('account')->name('account.')->middleware(['auth'])->group(function (): void {
    Route::inertia('/', 'Account/Show/Page')->name('show');
    Route::inertia('connected-apps', 'Workspace::ConnectedApp/Index/Page')->name('connected-apps.index');
});
Route::inertia('w/{workspace}/deletion', 'Workspace::Workspace/Deletion/Page')->middleware('auth')->name('workspaces.deletion.show');

<?php

use Illuminate\Support\Facades\Route;

Route::inertia('w/{workspace}/settings', 'Workspace/Settings/General/Page')->middleware('auth')->name('workspace.settings');

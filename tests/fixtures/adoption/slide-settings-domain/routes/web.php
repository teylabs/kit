<?php

use Illuminate\Support\Facades\Route;

Route::inertia('decks/{deck}/edit', 'Deck/Edit/Page')->middleware('auth')->name('decks.edit');

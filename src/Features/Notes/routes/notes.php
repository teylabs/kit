<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Notes\Http\NoteController;

Route::middleware(['auth:web', 'concierge.active'])->prefix('workspaces/{workspace}')->whereNumber('workspace')->group(function (): void {
    Route::get('notes', [NoteController::class, 'index']);
    Route::get('notes/{note}', [NoteController::class, 'show'])->whereNumber('note');
    Route::post('notes', [NoteController::class, 'store']);
    Route::put('notes/{note}', [NoteController::class, 'update'])->whereNumber('note');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->whereNumber('note');
});

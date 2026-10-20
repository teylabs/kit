<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Notes\Http\NoteController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

WorkspaceRoutes::define(function (bool $full): void {
    $index = Route::get('notes', [NoteController::class, 'index']);
    if ($full) {
        $index->name('workspaces.notes.index');
    }
    Route::get('notes/{note}', [NoteController::class, 'show'])->whereNumber('note');
    Route::post('notes', [NoteController::class, 'store']);
    Route::put('notes/{note}', [NoteController::class, 'update'])->whereNumber('note');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->whereNumber('note');
});

<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Core\HomeRoute;
use Tey\Kit\Features\Notes\Http\NoteController;
use Tey\Kit\Features\Notes\Http\NoteMediaController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

// The notes wall and its writes, and each attachment behind the same visibility as its note.
WorkspaceRoutes::define(function (bool $full): void {
    Route::controller(NoteController::class)->prefix('notes')->group(function () use ($full): void {
        WorkspaceRoutes::page(Route::get('', 'index'), $full, 'notes', 'notes.index', 'Notes', HomeRoute::parent());
        Route::post('', 'store')->name($full ? 'notes.store' : 'short.notes.store');
        // A note's own address answers its JSON, and sends a browser to the wall; its crumb sits under the wall's.
        WorkspaceRoutes::page(Route::get('{note}', 'show')->whereNumber('note'), $full, 'notes', 'notes.show', 'Note', ['notes', 'notes.index']);
        Route::put('{note}', 'update')->whereNumber('note')->name($full ? 'notes.update' : 'short.notes.update');
        Route::delete('{note}', 'destroy')->whereNumber('note')->name($full ? 'notes.destroy' : 'short.notes.destroy');
    });
    Route::controller(NoteMediaController::class)->prefix('notes/{note}/media')->whereNumber('note')->group(function () use ($full): void {
        Route::get('{media}', 'show')->whereUuid('media')->name($full ? 'notes.media.show' : 'short.notes.media.show');
    });
});

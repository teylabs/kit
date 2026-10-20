<?php

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Notes\Http\NoteController;
use Tey\Kit\Features\Notes\Models\Note;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

WorkspaceRoutes::define(function (bool $full): void {
    WorkspaceRoutes::page(Route::get('notes', [NoteController::class, 'index']), $full, 'notes', 'workspaces.notes.index', 'Notes');
    // A record's crumb reads as the record; it is looked up in its workspace, as the page's own request is over by the time the shell asks.
    WorkspaceRoutes::page(Route::get('notes/{note}', [NoteController::class, 'show'])->whereNumber('note'), $full, 'notes', 'workspaces.notes.show', static fn (int $workspace, int $note): string => app(TenantBoundary::class)->run($workspace, fn (): string => Note::query()->find($note)?->breadcrumbTitle() ?? 'Note'), ['notes', 'workspaces.notes.index']);
    Route::post('notes', [NoteController::class, 'store']);
    Route::put('notes/{note}', [NoteController::class, 'update'])->whereNumber('note');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->whereNumber('note');
});

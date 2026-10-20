<?php

namespace Tey\Kit\Features\Notes;

use Illuminate\Support\ServiceProvider;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Notes\Integrations\Mcp\CreateNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\DeleteNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\ListNotes;
use Tey\Kit\Features\Notes\Integrations\Mcp\ReadNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\UpdateNote;

final class NotesProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->routes(__DIR__.'/routes/notes.php', 'web', $routes)
            ->tools(in_array('mcp', $features, true) ? [ListNotes::class, ReadNote::class, CreateNote::class, UpdateNote::class, DeleteNote::class] : [])
            ->navigation('notes.read', 'Notes', 'notes', order: 10)
            ->scopes(['notes.read' => 'Read workspace notes', 'notes.write' => 'Create, update and delete workspace notes'])
            ->abilities(['notes.read' => 'workspace.notes', 'notes.write' => 'workspace.notes'])
            ->capabilities(['workspace.notes'])
            ->errors(['note_not_found' => 404, 'stale_note' => 409, 'note_intent_conflict' => 409], ['stale_note' => 'This note changed since you opened the form. Reload and review it before trying again.']);
    }
}

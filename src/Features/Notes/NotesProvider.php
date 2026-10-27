<?php

namespace Tey\Kit\Features\Notes;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Notes\Console\ImportNotesCommand;
use Tey\Kit\Features\Notes\Integrations\Mcp\DeleteNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\ListNotes;
use Tey\Kit\Features\Notes\Integrations\Mcp\ReadNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\WriteNote;
use Tey\Kit\Features\Notes\Models\Note;
use Tey\Kit\Features\Notes\ViewModels\NotesWidget;

/**
 * Workspace notes: the wall, the dashboard widget and the MCP tools, over one set of operations. Three abilities on one capability: `notes.read`,
 * `notes.write`, and `notes.moderate` (changing other people's notes), which is its own permission and never follows from writing.
 */
final class NotesProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->routes(__DIR__.'/routes/notes.php', 'web', $routes)
            ->navigation('notes.read', 'Notes', 'notes', order: 10)
            ->scopes(['notes.read' => 'Read workspace notes', 'notes.write' => 'Create, change and delete your workspace notes'])
            ->abilities(['notes.read' => 'workspace.notes', 'notes.write' => 'workspace.notes', 'notes.moderate' => 'workspace.notes'])
            ->capabilities(['workspace.notes'])
            // Defaults name only the roles the kit configures (owner, admin, viewer); an app with its own writing role grants it workspace.notes.manage.
            ->permission('workspace.notes.view', ['owner', 'admin', 'viewer'], 'workspace.notes', ['notes.read'])
            ->permission('workspace.notes.manage', ['owner', 'admin'], 'workspace.notes', ['notes.write'])
            ->permission('workspace.notes.moderate', ['owner', 'admin'], 'workspace.notes', ['notes.moderate'])
            ->widget('notes.recent', NotesWidget::class, 0, 'notes.read')
            ->errors(['note_not_found' => 404, 'note_not_yours' => 403, 'note_subject_unavailable' => 404, 'stale_note' => 409, 'note_intent_conflict' => 409], ['stale_note' => 'This note changed since you opened it. Reload and review it before trying again.', 'note_not_yours' => 'Only the person who wrote this note, or someone who moderates notes, can change it.']);
        // Contributes after mcp, which checks capability tools, and after declaring the capability they name.
        if (in_array('mcp', $features, true)) {
            // The operation's own codes reach the assistant as they are; anything else would be operation_failed.
            $registry->capabilityTools([ListNotes::class, ReadNote::class, WriteNote::class, DeleteNote::class])
                ->mcpRefusalCodes(['note_not_found', 'note_not_yours', 'note_subject_unavailable', 'stale_note', 'note_intent_conflict']);
        }
    }

    public function boot(): void
    {
        // Media rows name the note by alias, never by class.
        Relation::morphMap(['kit-note' => Note::class]);
        $this->commands([ImportNotesCommand::class]);
    }
}

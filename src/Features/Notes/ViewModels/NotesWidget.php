<?php

namespace Tey\Kit\Features\Notes\ViewModels;

use RuntimeException;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\SeeAll;
use Tey\Kit\Features\Notes\Composer;
use Tey\Kit\Features\Notes\Data\NoteComposerData;
use Tey\Kit\Features\Notes\Data\NoteRecord;
use Tey\Kit\Features\Notes\NoteUrls;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;
use Tey\Kit\Features\SessionUi\Dashboard\Widget;
use Tey\Kit\Features\SessionUi\Dashboard\WidgetEmptyData;

/** The dashboard's notes card (`notes.recent`, behind notes.read): the composer for someone who may write, and the latest notes, pinned first. */
#[TypeScript]
final class NotesWidget extends Widget
{
    public const LIMIT = 6;

    /** @var list<NoteRecord>|null */
    private ?array $notes = null;

    public function title(): string
    {
        return 'Notes';
    }

    public function component(): string
    {
        return 'notes';
    }

    // A task in main: writing a note is the viewer's own work, and the composer and the two-up grid of notes need the width. Not a feed: the dashboard's one
    // feed is Recent activity.
    /** @return 'task'|'feed'|'status'|'setup' */
    public function kind(): string
    {
        return 'task';
    }

    /** @return 'main'|'aside' */
    public function region(): string
    {
        return 'main';
    }

    public function state(): string
    {
        return $this->notes() === [] && $this->composer() === null ? 'empty' : 'ready';
    }

    public function empty(): ?WidgetEmptyData
    {
        return $this->state() === 'empty' ? new WidgetEmptyData('No notes yet', 'Notes people write in this workspace show up here.') : null;
    }

    public function composer(): ?NoteComposerData
    {
        try {
            app(Access::class)->authorize($this->context->principal, 'notes.write');
        } catch (RuntimeException) {
            return null;
        }

        return Composer::for($this->context->request, $this->context->workspace);
    }

    /** @return list<NoteRecord> */
    public function notes(): array
    {
        return $this->notes ??= array_slice(app(WorkspaceNotes::class)->listing($this->context->principal), 0, self::LIMIT);
    }

    // The latest notes are a list, so "All notes" is the card's see-all, in its footer after the last note (until v0.19.0 it was a header action).
    public function seeAll(): ?SeeAll
    {
        return SeeAll::to('All notes', NoteUrls::index($this->context->workspace));
    }
}

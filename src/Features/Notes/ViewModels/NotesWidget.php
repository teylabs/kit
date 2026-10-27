<?php

namespace Tey\Kit\Features\Notes\ViewModels;

use RuntimeException;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Notes\Composer;
use Tey\Kit\Features\Notes\Data\NoteComposerData;
use Tey\Kit\Features\Notes\Data\NoteRecord;
use Tey\Kit\Features\Notes\NoteUrls;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;
use Tey\Kit\Features\SessionUi\Dashboard\Widget;
use Tey\Kit\Features\SessionUi\Dashboard\WidgetEmptyData;
use Tey\Kit\Features\SessionUi\Dashboard\WidgetSpanData;

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

    public function span(): WidgetSpanData
    {
        return new WidgetSpanData(md: 6, lg: 8);
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

    public function actions(): array
    {
        return [new Action('All notes', NoteUrls::index($this->context->workspace), 'get', key: 'notes')];
    }
}

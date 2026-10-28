<?php

namespace Tey\Kit\Features\Notes\Lists;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Lists\ListEmptyData;
use Tey\Kit\Features\Core\Lists\ListItemData;
use Tey\Kit\Features\Core\Lists\ScrollListViewModel;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Notes\Data\NoteItemData;
use Tey\Kit\Features\Notes\NoteUrls;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;
use Tey\Kit\Features\Workspaces\Workspaces;

/** The notes wall: the same notes `WorkspaceNotes::listing()` returns (pinned first, then newest), paged as the viewer scrolls. */
final class NotesList extends ScrollListViewModel
{
    /** @param  array{colour?: string}  $filters */
    public function __construct(Request $request, int $workspace, private Principal $principal, private array $filters = [])
    {
        parent::__construct($request, $workspace);
    }

    public function key(): string
    {
        return 'notes';
    }

    public function title(): string
    {
        return 'Notes';
    }

    protected function perPage(): int
    {
        return 24;
    }

    protected function activeWorkspace(): int|string|null
    {
        return Workspaces::current()?->getKey();
    }

    protected function query(): Builder
    {
        return app(WorkspaceNotes::class)->query($this->principal, $this->filters);
    }

    protected function row(Model $model): ListItemData
    {
        $operations = app(WorkspaceNotes::class);
        $record = $operations->records([$model])[0];
        $mine = $operations->canChange($this->principal, $model) && $this->writable();
        $url = NoteUrls::note((int) $this->workspace, (int) $model->getKey());

        return new NoteItemData((string) $model->getKey(), Str::limit(trim(Str::before($record->body, "\n")), 80) ?: 'Note', $record,
            $mine ? new Action('Edit', $url, 'put', key: 'edit') : null,
            $mine ? new Action('Delete', $url, 'delete', confirm: ['title' => 'Delete this note?', 'description' => 'The note and its attachments are removed for everyone in this workspace.', 'label' => 'Delete note'], key: 'delete', destructive: true) : null,
        );
    }

    protected function empty(): ListEmptyData
    {
        return isset($this->filters['colour'])
            ? new ListEmptyData('first-use', 'No notes in this colour', 'Pick another colour, or show all notes.')
            : new ListEmptyData('first-use', 'No notes yet', 'Write the first one above.');
    }

    private ?bool $writable = null;

    private function writable(): bool
    {
        if ($this->writable === null) {
            try {
                app(Access::class)->authorize($this->principal, 'notes.write');
                $this->writable = true;
            } catch (RuntimeException) {
                $this->writable = false;
            }
        }

        return $this->writable;
    }
}

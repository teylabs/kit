<?php

namespace Tey\Kit\Features\Notes\Http;

use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Notes\Data\NoteRecord;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;
use Tey\Kit\Features\Notes\ViewModels\IndexNoteViewModel;
use Tey\Kit\Features\Notes\ViewModels\ShowNoteViewModel;
use Tey\Kit\Features\Workspaces\TenantBoundary;

class NoteController
{
    public function index(Request $request, int $workspace): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace) {
            $principal = new Principal((int) $request->user()->getKey());
            $notes = array_map(fn ($note) => $note->toArray(), app(WorkspaceNotes::class)->listing($principal));
            $canWrite = false;
            try {
                app(Access::class)->authorize($principal, 'notes.write');
                $canWrite = true;
            } catch (RuntimeException) {
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['notes' => $notes, 'can_write' => $canWrite]);
            }
            $base = WorkspaceUrl::to($workspace, 'notes', 'notes');
            $actions = [];
            foreach ($notes as $note) {
                $actions[$note['id']] = ['url' => $base.'/'.$note['id'], 'update_intent' => (string) Str::uuid(), 'delete_intent' => (string) Str::uuid()];
            }

            return Inertia::render('Workspace/Note/Index/Page', new IndexNoteViewModel('Notes', array_map(fn (array $note) => NoteRecord::from($note), $notes), $canWrite, $base, (string) Str::uuid(), $actions));
        });
    }

    public function show(Request $request, int $workspace, int $note): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace, $note) {
            $record = app(WorkspaceNotes::class)->read(new Principal((int) $request->user()->getKey()), ['id' => $note]);
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json($record->toArray());
            }

            return Inertia::render('Workspace/Note/Show/Page', new ShowNoteViewModel('Note', $record, WorkspaceUrl::to($workspace, 'notes', 'notes')));
        });
    }

    public function store(Request $request, int $workspace): mixed
    {
        return $this->mutate($request, $workspace, 'create');
    }

    public function update(Request $request, int $workspace, int $note): mixed
    {
        return $this->mutate($request, $workspace, 'update', $note);
    }

    public function destroy(Request $request, int $workspace, int $note): mixed
    {
        return $this->mutate($request, $workspace, 'delete', $note);
    }

    private function mutate(Request $request, int $workspace, string $operation, ?int $note = null): mixed
    {
        $input = $request->except(['_token', '_method']);
        if (array_key_exists('id', $input)) {
            throw new RuntimeException('unknown_arguments');
        }
        if ($note !== null) {
            $input['id'] = $note;
        }
        $result = app(TenantBoundary::class)->run($workspace, fn () => app(WorkspaceNotes::class)->mutate(new Principal((int) $request->user()->getKey()), $operation, $input));

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($result->toArray(), $operation === 'create' ? 201 : 200) : redirect(WorkspaceUrl::to($workspace, 'notes', 'notes'));
    }
}

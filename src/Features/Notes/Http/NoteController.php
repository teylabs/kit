<?php

namespace Tey\Kit\Features\Notes\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Concerns\Toastable;
use Tey\Kit\Features\Core\Http\OperationForm;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Notes\Colours;
use Tey\Kit\Features\Notes\Composer;
use Tey\Kit\Features\Notes\Lists\NotesList;
use Tey\Kit\Features\Notes\NoteUrls;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;
use Tey\Kit\Features\Notes\ViewModels\NotesIndexViewModel;

/** The notes wall and its writes. Every read and write is a `WorkspaceNotes` operation, the same ones the MCP tools call. */
class NoteController
{
    use Toastable;

    /** The fields each form sends. The note's id comes from the route, never the body. */
    private const FIELDS = [
        'create' => ['body', 'colour', 'pinned', 'subject_type', 'subject_id', 'intent_key', 'files'],
        'update' => ['body', 'colour', 'pinned', 'remove_attachments', 'expected_version', 'intent_key', 'files'],
        'delete' => ['expected_version', 'intent_key'],
    ];

    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }

    public function index(Request $request, int $workspace): mixed
    {
        $principal = $this->principal($request);

        $colour = in_array($request->query('colour'), Colours::keys(), true) ? (string) $request->query('colour') : null;
        $filters = $colour === null ? [] : ['colour' => $colour];
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['notes' => array_map(fn ($note) => $note->toArray(), app(WorkspaceNotes::class)->listing($principal, $filters))]);
        }
        // Refused before the page renders, so someone without notes.read gets the error page, not an empty wall.
        app(Access::class)->authorize($principal, 'notes.read');
        $writable = true;
        try {
            app(Access::class)->authorize($principal, 'notes.write');
        } catch (RuntimeException) {
            $writable = false;
        }

        // Rendered here, inside the tenant context, so the wall's deferred rows load in this workspace.
        return Inertia::render('Workspace/Notes/Index/Page', new NotesIndexViewModel('Notes', $writable ? Composer::for($request, $workspace) : null, Colours::options(), $colour, NoteUrls::index($workspace), new NotesList($request, $workspace, $principal, $filters)))->toResponse($request);
    }

    public function show(Request $request, int $workspace, int $note): mixed
    {
        $principal = $this->principal($request);
        $record = app(WorkspaceNotes::class)->read($principal, ['id' => $note]);

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($record->toArray()) : redirect(NoteUrls::index($workspace));
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
        $principal = $this->principal($request);
        // Only the fields this form sends reach the operation; a stray browser field (or an `id` in the body) is dropped on a page visit, not refused.
        $input = OperationForm::input($request, self::FIELDS[$operation]);
        // Booleans arrive as strings in a multipart form.
        if (isset($input['pinned']) && is_string($input['pinned'])) {
            $input['pinned'] = in_array($input['pinned'], ['1', 'true'], true) ? true : (in_array($input['pinned'], ['0', 'false'], true) ? false : $input['pinned']);
        }
        // Only a JSON caller can still send an id in the body (a page visit's input is already filtered); it is refused, as before.
        if (array_key_exists('id', $input)) {
            throw new RuntimeException('unknown_arguments');
        }
        if ($note !== null) {
            $input['id'] = $note;
        }
        $result = OperationForm::run($request, fn () => app(WorkspaceNotes::class)->mutate($principal, $operation, $input));
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($result->toArray(), $operation === 'create' ? 201 : 200);
        }
        $this->toast(match ($operation) {
            'create' => 'Note added',
            'update' => 'Note saved',
            'delete' => 'Note deleted',
        })->from($operation === 'delete' ? 'delete' : 'save');

        return back(fallback: NoteUrls::index($workspace));
    }
}

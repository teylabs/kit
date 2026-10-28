<?php

namespace Tey\Kit\Features\Notes\Operations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Notes\Colours;
use Tey\Kit\Features\Notes\Data\NoteChange;
use Tey\Kit\Features\Notes\Data\NoteRecord;
use Tey\Kit\Features\Notes\Models\Note;
use Tey\Kit\Features\Notes\NoteUrls;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * The notes operations, principal-first: the pages and the MCP tools call these and nothing else, so both transports authorize, validate, replay and
 * refuse the same way. Call inside the workspace's tenant context.
 *
 * Reading needs `notes.read`; writing `notes.write`. Changing or deleting someone else's note also needs moderation: the workspace owner, or
 * `notes.moderate`, which is its own permission and never follows from `notes.write`. A note about a subject is visible only to a viewer the subject's
 * resolver lets see it. Every write carries an `intent_key` (a retry replays the first result; the key with other input is `note_intent_conflict`),
 * and an update or delete an `expected_version` (`stale_note` when it moved).
 */
class WorkspaceNotes
{
    public const LIST_LIMIT = 50;

    /**
     * The newest notes, pinned first: the workspace's own, or one subject's with `subject_type` and `subject_id`. `colour` filters.
     *
     * @return list<NoteRecord>
     */
    public function listing(Principal $principal, array $input = []): array
    {
        $query = $this->query($principal, $input);

        return $this->records($query->limit(self::LIST_LIMIT)->get()->all());
    }

    /** The notes a listing shows, as an ordered query (pinned, then newest, then id): for a paged list over the same rules. */
    public function query(Principal $principal, array $input = []): Builder
    {
        app(Access::class)->authorize($principal, 'notes.read');
        $this->only($input, ['colour', 'subject_type', 'subject_id']);
        $data = Validator::make($input, ['colour' => ['nullable', Rule::in(Colours::keys())], 'subject_type' => ['nullable', 'string', 'required_with:subject_id'], 'subject_id' => ['nullable', 'integer', 'min:1', 'required_with:subject_type']])->validate();
        $query = Note::query()->with('media');
        if (isset($data['subject_type'])) {
            $this->subject($principal, $data['subject_type'], (int) $data['subject_id']);
            $query->where('subject_type', $data['subject_type'])->where('subject_id', (int) $data['subject_id']);
        } else {
            $query->whereNull('subject_type');
        }

        return $query->when(isset($data['colour']), fn (Builder $q) => $q->where('colour', $data['colour']))->orderByDesc('pinned')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function read(Principal $principal, array $input): NoteRecord
    {
        app(Access::class)->authorize($principal, 'notes.read');
        $this->only($input, ['id']);
        $data = Validator::make($input, ['id' => ['required', 'integer', 'min:1']])->validate();

        return $this->records([$this->visible($principal, (int) $data['id'])])[0];
    }

    /** Whether the principal may change or delete the note: their own, or moderation. Never throws. */
    public function canChange(Principal $principal, Note $note): bool
    {
        try {
            $this->authorizeChange($principal, $note);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * `create` (body, colour, pinned, subject_type, subject_id), `update` (id, expected_version, then any of body, colour, pinned, remove_attachments)
     * or `delete` (id, expected_version), each with `intent_key`. `files` (uploads, from the page only) attach to a create or update.
     */
    public function mutate(Principal $principal, string $operation, array $input): NoteChange
    {
        if (! in_array($operation, ['create', 'update', 'delete'], true)) {
            throw new RuntimeException('unknown_arguments');
        }
        app(Access::class)->authorize($principal, 'notes.write');
        $media = (array) config('kit.notes.media', []);
        $rules = ['intent_key' => ['required', 'string', 'max:120']];
        if ($operation !== 'create') {
            $rules += ['id' => ['required', 'integer', 'min:1'], 'expected_version' => ['required', 'integer', 'min:1']];
        }
        if ($operation === 'create') {
            $rules += ['body' => ['required', 'string', 'max:5000'], 'colour' => ['nullable', Rule::in(Colours::keys())], 'pinned' => ['nullable', 'boolean'], 'subject_type' => ['nullable', 'string', 'required_with:subject_id'], 'subject_id' => ['nullable', 'integer', 'min:1', 'required_with:subject_type']];
        }
        if ($operation === 'update') {
            $rules += ['body' => ['sometimes', 'required', 'string', 'max:5000'], 'colour' => ['sometimes', Rule::in(Colours::keys())], 'pinned' => ['sometimes', 'boolean'], 'remove_attachments' => ['sometimes', 'array', 'max:20'], 'remove_attachments.*' => ['uuid']];
        }
        if ($operation !== 'delete') {
            $rules += ['files' => ['sometimes', 'array', 'max:'.(int) ($media['max_files'] ?? 5)], 'files.*' => ['file', 'max:'.(int) ($media['max_kb'] ?? 10240), 'mimetypes:'.implode(',', (array) ($media['accept'] ?? ['image/png', 'image/jpeg', 'application/pdf']))]];
        }
        $this->only($input, array_keys(array_filter($rules, fn (string $key): bool => ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY)));
        $data = Validator::make($input, $rules)->validate();
        $files = array_values(array_filter((array) ($data['files'] ?? []), fn ($file): bool => $file instanceof UploadedFile));
        $fields = array_diff_key($data, ['intent_key' => true, 'files' => true]);
        ksort($fields);
        $fingerprint = hash('sha256', json_encode([$operation, $fields, array_map(fn (UploadedFile $file): string => (string) hash_file('sha256', $file->getRealPath()), $files)], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($principal, $operation, $data, $files, $fingerprint): NoteChange {
            $workspace = app(Access::class)->authorize($principal, 'notes.write');
            Workspaces::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $previous = DB::table('kit_note_intents')->where('workspace_id', $workspace->id)->where('actor_id', $principal->actorId)->where('intent_key', $data['intent_key'])->first();
            if ($previous) {
                if ($previous->fingerprint !== $fingerprint) {
                    throw new RuntimeException('note_intent_conflict');
                }

                return NoteChange::from(json_decode($previous->result, true, flags: JSON_THROW_ON_ERROR));
            }
            if ($operation === 'create') {
                if (isset($data['subject_type'])) {
                    $this->subject($principal, $data['subject_type'], (int) $data['subject_id']);
                }
                $note = new Note(['author_id' => $principal->actorId, 'subject_type' => $data['subject_type'] ?? null, 'subject_id' => $data['subject_id'] ?? null, 'body' => $data['body'], 'colour' => $data['colour'] ?? Colours::default(), 'pinned' => (bool) ($data['pinned'] ?? false), 'version' => 1]);
            } else {
                $note = $this->visible($principal, (int) $data['id'], lock: true);
                $this->authorizeChange($principal, $note);
                if ($note->version !== (int) $data['expected_version']) {
                    throw new RuntimeException('stale_note');
                }
            }
            if ($operation === 'delete') {
                $note->delete();
            } else {
                if ($operation === 'update') {
                    $note->fill(array_intersect_key($data, array_flip(['body', 'colour', 'pinned'])));
                    $note->version++;
                }
                $note->save();
                foreach ((array) ($data['remove_attachments'] ?? []) as $uuid) {
                    $note->getMedia('attachments')->firstWhere('uuid', $uuid)?->delete();
                }
                foreach ($files as $file) {
                    $note->addMedia($file)->preservingOriginal()->toMediaCollection('attachments');
                }
                $note->load('media');
            }
            $change = new NoteChange((int) $note->id, (int) $workspace->id, $operation === 'delete', $operation === 'delete' ? null : $this->records([$note])[0]);
            app(Audit::class)->write($principal->actorId, (int) $workspace->id, 'workspace.note.'.$operation.'d', ['note_id' => $note->id, 'version' => $note->version, 'by_author' => $note->author_id === $principal->actorId]);
            DB::table('kit_note_intents')->insert(['workspace_id' => $workspace->id, 'actor_id' => $principal->actorId, 'intent_key' => $data['intent_key'], 'fingerprint' => $fingerprint, 'result' => $change->toJson(), 'created_at' => now()]);

            return $change;
        });
    }

    /** A note the principal may see, or `note_not_found`: another workspace's, a deleted one and one about a subject they cannot see all look absent. */
    public function visible(Principal $principal, int $id, bool $lock = false): Note
    {
        $note = Note::query()->with('media')->whereKey($id)->when($lock, fn (Builder $q) => $q->lockForUpdate())->first() ?? throw new RuntimeException('note_not_found');
        if ($note->subject_type !== null) {
            try {
                $this->subject($principal, $note->subject_type, (int) $note->subject_id);
            } catch (RuntimeException) {
                throw new RuntimeException('note_not_found');
            }
        }

        return $note;
    }

    /** Fail closed: an unregistered alias, a missing record and a resolver that says no or throws are all `note_subject_unavailable`. */
    private function subject(Principal $principal, string $alias, int $id): object
    {
        $resolver = app(Registry::class)->noteSubjectFor($alias) ?? throw new RuntimeException('note_subject_unavailable');
        try {
            $record = $resolver['model']::query()->find($id);
            $allowed = $record !== null && ($resolver['view'])($principal, $record) === true;
        } catch (\Throwable) {
            $allowed = false;
        }
        if (! $allowed) {
            throw new RuntimeException('note_subject_unavailable');
        }

        return $record;
    }

    /** Your own note, or moderation: the workspace owner, or `notes.moderate`. `notes.write` alone never changes another person's note. */
    private function authorizeChange(Principal $principal, Note $note): void
    {
        if ($note->author_id !== null && $note->author_id === $principal->actorId) {
            return;
        }
        $user = RequestMemo::user($principal->actorId);
        $member = $user ? RequestMemo::membership($user, Workspaces::required()) : null;
        if ($member !== null && $member->isActive() && app(Concierge::class)->isOwner($member)) {
            return;
        }
        try {
            app(Access::class)->authorize($principal, 'notes.moderate');
        } catch (RuntimeException) {
            throw new RuntimeException('note_not_yours');
        }
    }

    /**
     * @param  list<Note>  $notes
     * @return list<NoteRecord>
     */
    public function records(array $notes): array
    {
        $model = Users::model();
        $authors = $model::query()->whereKey(array_values(array_unique(array_filter(array_map(fn (Note $note) => $note->author_id, $notes)))))->pluck('name', (new $model)->getKeyName());
        $subjects = [];

        return array_map(function (Note $note) use ($authors, &$subjects): NoteRecord {
            $subject = null;
            if ($note->subject_type !== null) {
                $key = $note->subject_type.':'.$note->subject_id;
                $subjects[$key] ??= $this->describe($note->subject_type, (int) $note->subject_id);
                $subject = $subjects[$key];
            }
            $attachments = $note->getMedia('attachments')->map(fn ($media): array => [
                'id' => (string) $media->uuid,
                'name' => (string) $media->file_name,
                'mime' => (string) $media->mime_type,
                'size' => (int) $media->size,
                'url' => NoteUrls::media((int) $note->workspace_id, (int) $note->id, (string) $media->uuid),
                'thumb_url' => $media->hasGeneratedConversion('thumb') ? NoteUrls::media((int) $note->workspace_id, (int) $note->id, (string) $media->uuid, true) : null,
            ])->values()->all();

            return new NoteRecord((int) $note->id, (int) $note->workspace_id, $note->author_id, $note->author_id === null ? null : (isset($authors[$note->author_id]) ? (string) $authors[$note->author_id] : null), (string) $note->body, (string) $note->colour, (bool) $note->pinned, (int) $note->version, $subject, $attachments, (string) $note->created_at?->toIso8601String(), (string) $note->updated_at?->toIso8601String());
        }, $notes);
    }

    /** @return array{type: string, id: int, label: string, url: string|null} */
    private function describe(string $alias, int $id): array
    {
        $resolver = app(Registry::class)->noteSubjectFor($alias);
        $record = $resolver === null ? null : $resolver['model']::query()->find($id);

        return ['type' => $alias, 'id' => $id, 'label' => $record === null ? 'Unavailable' : (string) ($resolver['label'])($record), 'url' => $record === null || $resolver['url'] === null ? null : ($resolver['url'])($record)];
    }

    private function only(array $input, array $keys): void
    {
        if (array_diff(array_keys($input), $keys)) {
            throw new RuntimeException('unknown_arguments');
        }
    }
}

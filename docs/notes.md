# Notes

`notes` is an opt-in feature: workspace notes with a quick-add composer, a wall of cards, attachments, a dashboard widget and MCP tools. All of them run through one set of operations. It requires `workspaces`, `access` and `session-ui`. Adopt it with `php artisan kit:add notes`, then `php artisan migrate` and `php artisan kit:permissions:sync`.

## What a note is

A row in `kit_notes`:

- the workspace;
- the author;
- an optional subject (a registered record the note is about);
- the body (up to 5,000 characters);
- a colour;
- pinned;
- a version;
- timestamps and soft deletes.

Attachments are medialibrary files in the note's `attachments` collection.

## Access

One capability, `workspace.notes`, gates three abilities, each tied to its own permission:

| Ability | Permission | Default roles | Allows |
|---|---|---|---|
| `notes.read` | `workspace.notes.view` | owner, admin, member, viewer | Reading notes and opening their attachments |
| `notes.write` | `workspace.notes.manage` | owner, admin, member | Writing notes, and changing or deleting **your own** |
| `notes.moderate` | `workspace.notes.moderate` | owner, admin | Changing or deleting **anyone's** note |

Changing someone else's note needs moderation: you are the workspace owner, or you hold `notes.moderate`. `notes.write` never implies it. Without it, the change is refused as `note_not_yours` (403). To let every member change any note, grant `workspace.notes.moderate` to the member role. The token scopes `notes.read` and `notes.write` let a connection be granted notes alone.

## Operations

`Tey\Kit\Features\Notes\Operations\WorkspaceNotes` takes the principal first. The pages and the MCP tools call it and nothing else. Call it inside the workspace's tenant context.

- `listing($principal, [colour?, subject_type?, subject_id?])` returns the newest 50 notes, pinned first: the workspace's own notes, or one subject's.
- `query(...)` is the same selection as an ordered query, for a paged list.
- `read($principal, ['id'])` returns one note.
- `mutate($principal, 'create'|'update'|'delete', $input)` writes:
  - Every write carries an `intent_key`. A retry with the same key replays the first result; the same key with other input is `note_intent_conflict` (409).
  - An update or delete carries `expected_version`. A note that moved since is `stale_note` (409).
  - `files` (uploads from the page) attach on a create or update, and `remove_attachments` (uuids) detaches.

Every write is audited as `workspace.note.created`, `updated` or `deleted`. A note in another workspace, a deleted note, and a note about a record the viewer may not see are all `note_not_found` (404).

## Subjects: notes about a record

An app makes its records notable without the kit knowing them:

```php
app(Registry::class)->noteSubject(
    'project',                                                   // stored in kit_notes.subject_type; never a class name
    Project::class,
    view: fn (Principal $principal, Project $project): bool => Gate::forUser(Users::find($principal->actorId))->allows('view', $project),
    label: fn (Project $project): string => $project->name,
    url: fn (Project $project): ?string => route('projects.show', $project),
);
```

A note about a subject is visible only to a viewer that `view` allows. It is listed with that subject (`subject_type`, `subject_id`), not on the workspace wall. An alias no app registered, a missing record, and a `view` that says no or throws are all refused as `note_subject_unavailable`, so the check fails closed.

## Composer hooks

`Registry::noteComposer(fn (Request $request): array)` adds to the composer for a request:

- `options`: extra links beside Save, `list<array{key, label, icon?, url?}>`;
- `subject_search`: a URL for the app's own "link to record" picker.

Suggestions, AI or any domain search stay in the app behind this hook. A hook that throws adds nothing.

## Pages and widget

- `/@{workspace}/notes` (`pages/Workspace/Notes/Index/Page.vue`) shows the composer (for someone who may write), a colour filter, and the wall as a ScrollList, pinned first then newest.
  - Each card (`Partials/NoteCard.vue`) has Edit, Pin and Delete (with a confirmation) for someone who may change it.
  - Saving flashes a toast (`->toast('Note added')->from('save')`).
  - The composer (`Partials/NoteComposer.vue`) saves on the button or Cmd/Ctrl+Enter and keeps focus in the box.
- The dashboard widget `notes.recent` (behind `notes.read`, `resources/js/widgets/Notes.vue`) shows the composer for someone who may write, then the latest six notes.

## Attachments

`config('kit.notes.media')` sets `disk` (default `local`, private), `accept` (PNG, JPEG, PDF), `max_files` (5) and `max_kb` (10,240). Images get a 320×240 `thumb` at upload.

Files are served at `/@{workspace}/notes/{note}/media/{uuid}`, only to someone who may read the note, with `Cache-Control: private, no-store` and `nosniff`. An image shows in place; anything else downloads. Anything else, including another note's file, is a 404.

## Colours

`kit.notes.colours` lists `{key, label}`, and the first is the default. The kit's stubs have fills for yellow, pink, blue and green. A colour you add uses the card fill until you give it one in `Partials/colours.ts`.

## Coming from an earlier notes table

The feature never touches a `notes` table (from an earlier kit release) or an `app_notes` table (an app's own). `kit:add notes` and `kit:adopt notes` point either one out as attention that doesn't block. Copy their notes with:

```sh
php artisan kit:notes:import --from=notes --dry-run   # report only
php artisan kit:notes:import --from=notes
```

The import:

- reads the source and never alters, empties or drops it;
- marks each copy with its origin (`notes:42`), so a second run copies nothing twice;
- skips and reports rows already imported, soft-deleted rows, empty bodies, and rows whose workspace is gone;
- replaces a colour outside the palette with the default;
- needs `id`, `workspace_id` and `body`, and uses `author_id`, `colour`, `pinned` and timestamps when they are present.

A note with no author (an earlier kit release kept none) can be changed only by moderation.

## MCP

With `mcp` adopted, four capability tools (`#[Capability('workspace.notes')]`) call the same operations:

- `notes_list` and `notes_read` (read only);
- `notes_write`: creates, or changes with `id` and `expected_version`;
- `notes_delete`: destructive, so it asks for confirmation.

A retry with the same `intent_key` replays through the consent gate. Attachments are page-only.

## Coverage

`composer test:notes` runs on SQLite memory with synthetic users. It covers:

- tenant isolation;
- moderation: your own note, the owner, someone else's without `notes.moderate`, and with it;
- stale writes and intent replay;
- subjects;
- attachments and their gated media;
- the save toast and the dashboard widget;
- the MCP tools' operations;
- the legacy-table attention step and the import.

Not covered: no browser renders the composer, cards or widget, and the MCP tools are called directly, not over the transport.

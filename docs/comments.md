# Comments and notes: NoteCard, CommentItem, CommentForm and CommentThread

Four `@kit/ui` components draw a discussion on a record, a notes wall or a dashboard card. They are presentational. The server sends each comment, each composer and each action whole, and the components make no request of their own: a save is the Inertia `<Form>` its composer names, and a delete is an Inertia visit to its action. Nothing is fetched as JSON.

```vue
<CommentThread title="Comments" :comments="comments" :composer="composer" :only="['comments']" />
```

| Component | What it is |
| --- | --- |
| `NoteCard` | The card a comment or note sits on: a colour accent on its left or top edge, a header (avatar, author, the `timestamp` slot, the colour's meaning, and the `actions` slot on the right), the body (default slot) and a `footer` slot. Renders what it is given and does nothing else. |
| `QuickAction` | A quick action for CommentItem's `quick` slot or NoteCard's `actions`: `<QuickAction label="Mark done" :icon="Check" @click="…" />`. A ghost button with its icon and label; on a narrow card (the NoteCard is a size container) only the icon shows, and the label stays its accessible name and tooltip. On a narrow card the header's author, time and meaning cut off with an ellipsis before the actions. |
| `CommentItem` | One comment on a NoteCard with its time, body, files, subject, menu and replies, and its editor and reply composer in place. |
| `CommentForm` | The composer for a new comment, a reply or an edit. |
| `CommentThread` | A titled `PageSection`: the composer, then the comments (or groups of them), or an empty line. |

## The data

The types are exported from `@kit/ui`: `CommentData`, `CommentComposerData`, `CommentAuthor`, `CommentColour`, `CommentAttachment` and `CommentGroup`.

```ts
const comment: CommentData = {
    id: 12,
    body: 'Please check the measurements before Friday.',
    author: { name: 'Sam Reed', initials: 'SR', avatar_url: null },
    created_at: '2026-11-13T15:00:00Z',  // RFC 3339; shown relative, its full date on hover
    version: 3,                          // sent with every change: a comment changed meanwhile is refused, never overwritten
    colour: { key: 'urgent', label: 'Urgent', tone: 'danger', meaning: 'Needs an answer today' },
    attachments: [{ id: 'f1', name: 'plan.pdf', mime: 'application/pdf', url: '/files/f1', size: 20480 }],
    subject: { label: 'Project 7', url: '/projects/7' },
    noun: 'comment',                     // in labels: "Delete this comment?"; a note says 'note'
    editor: { /* a CommentComposerData whose submit is this comment's PUT or PATCH */ },
    reply: { /* a CommentComposerData for a reply, its parent in `fields` */ },
    delete: { label: 'Delete', url: '/comments/12', method: 'delete', confirm: { title: 'Delete this comment?', description: '…', label: 'Delete comment' } },
    replies: [],
};
```

An action or composer the viewer may not use is absent (or null), and the component offers nothing for it. These descriptors decide what is offered, not what is allowed: the server checks every request again.

A composer:

```ts
const composer: CommentComposerData = {
    submit: { label: 'Post', url: '/comments', method: 'post' },
    placeholder: 'Write a comment…',
    label: null,                                   // a visible label; without one, the placeholder names the box
    colours: [{ key: 'yellow', label: 'Yellow', tone: 'yellow' }, { key: 'blue', label: 'Blue', tone: 'blue' }],
    default_colour: 'yellow',
    media: { accept: ['image/png', 'application/pdf'], max_files: 3, max_kb: 10240, hint: 'PNG or PDF, up to 10 MB.' },  // null: no files
    fields: { subject_type: 'project', subject_id: 7 },  // posted with every save
    author: { name: 'Sam Reed', initials: 'SR' },        // the person writing, for the avatar
};
```

## Colours

A colour's `tone` picks its accent: `muted`, `info`, `success`, `warning` and `danger` on the kit's text tokens, and `yellow`, `pink`, `blue` and `green`, the notes feature's palette. Any other tone is muted (`COMMENT_TONES` lists them). The colour's `label` is read out with the author and the time, so a colour never carries meaning alone. Its `meaning`, when there is one, shows beside the time.

## What a save sends

`CommentForm` is an Inertia `<Form>` to `composer.submit.url`, with `preserveScroll` and the `only` you pass. It posts:

- `body` and `colour`;
- `intent_key`, fresh after each save, so a retry of a lost response replays it instead of writing twice;
- the composer's `fields`;
- `files`, the files chosen by drop, paste or Attach a file (the kit's BulkDrop), within `media`'s limits;
- for an edit (`comment` set): `expected_version`, and `remove_attachments`, the ids of the files ticked to remove.

A `put`, `patch` or `delete` submit is posted with `_method`, as Laravel reads it, so its files arrive as multipart. Errors show inline: `body`'s under the box (and linked to it), any other under the form.

`CommentItem`'s Delete asks first, with the action's `confirm` (title, description and the confirming button's label), then sends an Inertia DELETE with `expected_version` and an `intent_key`. A refused delete (a stale version arrives as an error) shows its message under the card. A delete whose `confirm` needs typing (`typed`) can't be asked from a menu, so, as with `DropdownAction`, it shows disabled with that reason and never runs; so does one the server sent `disabled`, with its `reason`.

## Behaviour

- **A new comment** rests as one line beside the writer's avatar. Focus opens it into the full box with its toolbar: the writer's avatar, Attach a file, the colours (radio buttons, from two colours up), then Cancel and the submit. It closes again when focus leaves it empty.
- **Keys.** With `enter-saves`, Enter saves and Shift+Enter starts a new line. Otherwise Cmd or Ctrl+Enter saves. Escape closes an empty composer, or cancels an edit.
- **After a save** a new comment's composer clears (text, files, colour) and focus returns to the box for the next one. An editor or reply composer emits `success`, and CommentItem closes it.
- **Edit** opens the editor in place of the card, filled in and focused. Cancel or a save puts the card back and returns focus to its menu.
- **Reply** opens a composer under the comment, focused.
- **Files** on a card are a `FileList`. An image whose thumbnail is still pending (`thumb_pending`) shows its type, never the original.

## Props, slots and events

**NoteCard:**

- Props: `accent` (`{ tone, label, meaning? }`), `author`, `variant` (`full`, or `compact`: one line, with no header), `density` (`default`: a bordered card; `compact`: no card, an inset row; `flush`: a filled tile that fills its cell), `accentEdge` (`left` or `top`), `accentWidth` (`thin`, `medium` or `wide`), `authorStyle` (`avatar-name`, `name` or `none`) and `as` (`article`, the default, named by its header; `div`; `li`).
- Slots: default, `timestamp`, `meta` (more of the header line, after the time and the colour's meaning), `actions` and `footer`.

**CommentItem:**

- Props: `comment`, plus `variant`, `density`, `accentEdge` and `accentWidth` (NoteCard's). A compact item shows no actions, files or replies. `interactive` false shows no actions at all (a read-only feed). `collapsible` cuts a long body with Show more (TextLong). `only` limits each visit's props.
- Opt-ins, all off by default: `time-format` `absolute` shows the date and time (DateTime `local`) in place of the relative time. `attachment-layout` `gallery` shows image files as a grid of square thumbnails, each a link to the file (a thumbnail still being made is a named placeholder, never the original), and keeps the other files in the FileList. `menu-icons` puts an icon on each menu row (Edit, Reply, Delete). Replies take the same three.
- Slots: `quick` (scoped by `comment`): the host's own buttons before the menu. `meta` (scoped by `comment`): added to the header line after the time, for a record link and its icon. `attachments` (scoped by `files` and `comment`): replaces the gallery and the file list. `footer` (scoped by `comment`): replaces the subject (a link when it has a `url`, text otherwise). Replies receive `meta` and `attachments` too.
- Event: `deleted`.

**CommentForm:**

- Props: `composer`, `comment` (makes it that comment's editor), `placeholder` (overrides the composer's), `enter-saves`, `only` and `avatar-size` (the resting avatar: `xs`, `sm`, `md` (the default) or `lg`).
- Slots: `before` and `after` (scoped by `composer` and `processing`): the host's own controls above and below the body, such as a record search or options. Their named inputs post with the form.
- Events: `start`, `finish`, `success`, `cancel` and `typing(text)`.
- Exposed: `focus()`, `text()`, `colour()`, `clear()` (empties the body, files and colour without saving, an editor's too; Cancel puts an editor back to its comment) and `submit()`.

**CommentThread:**

- Props: `title`, `comments`, `groups` (runs of comments shown together, `{ id, comments }[]`, in place of `comments`, even when it is empty or none of its groups has a comment), `composer`, `empty` (default "No comments yet."), `level` (the heading's), `interactive` and `only`.
- Opt-ins and slots passed through: `time-format`, `attachment-layout` and `menu-icons` to every CommentItem, its `meta` and `attachments` slots, and `composer-before` / `composer-after` as the composer's `before` and `after`.

## Hooks for tests

`data-comment-thread`, `data-comment` (the comment's id), `data-comment-menu`, `data-comment-edit`, `data-comment-reply`, `data-comment-delete`, `data-comment-text`, `data-comment-subject`, `data-comment-form` (with `data-open` and `data-editing`), `data-comment-body`, `data-comment-toolbar`, `data-comment-attach`, `data-comment-colours`, `data-colour-choice`, `data-comment-files`, `data-comment-existing`, `data-comment-cancel`, `data-comment-empty`, `data-comment-group`, and NoteCard's `data-note-card` (its density), `data-note-variant`, `data-accent-edge`, `data-tone`, `data-note-header`, `data-note-author`, `data-note-meaning` and `data-note-colour-name`.

## Coverage

The browser job (`comments.html`, on the real `@inertiajs/vue3`, with the test as the server) covers:

- axe in light and dark (color-contrast included), at rest and with the composer open;
- each accent width on each edge, the status tones on their tokens, an unknown tone falling back to muted, the four note colours distinct, and the flush tile;
- the article's accessible name (author, time, colour and meaning), compact as one line with no actions, files or replies, and no request for a pending thumbnail;
- the composer: resting, opening, closing on Escape, Enter as a new line, a colour by keyboard, a save on Ctrl+Enter with its fields, file and intent key, then cleared with focus kept, and a second save with a new key;
- an edit's `_method`, `expected_version` and removed file, with focus back on the menu, and Escape cancelling one;
- a delete's confirmation and its DELETE, with a refusal shown inline;
- a reply's composer, focused, posting its own fields.

`bin/typecheck-components.sh` compiles `typecheck/fixtures/Comments.vue`.

Not covered: a consumer app's own server, real file uploads to a disk, and browsers other than Chromium.

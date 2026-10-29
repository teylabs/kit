# ActivityStream

`ActivityStream` (`Kit/Feed/ActivityStream`, exported from `@kit/ui`) is a read-only stream of activity entries, newest first, optionally cut into days. It doesn't know where the entries came from: an audit log, a feed service and one record's timeline all draw through it. The dashboard's Recent activity widget uses it.

```vue
<ActivityStream :entries="entries" />
<ActivityStream :entries="entries" :days="false" dense empty="No changes yet." />
```

## Props

| Prop | Default | Notes |
| --- | --- | --- |
| `entries` | required | `ActivityEntry[]`, newest first |
| `days` | `true` | a section per `entry.day`, with the day's label as its heading and `aria-label`; off, one unlabelled list |
| `empty` | "Nothing has happened yet." | the EmptyState title when there are no entries |
| `dense` | `false` | tighter rows, for a card or a side panel |
| `live` | `false` | makes the stream a polite live region, for a feed the viewer watches update. Off by default: a stream that's there when the page renders is noise to announce |
| `more`, `loading` | `false` | show the Load more button, and disable it while a page loads |

The `load-more` event asks for the next page. When the press brings the last page, focus moves to the first new entry. Otherwise it stays on the button.

## Entries

The server maps its records into `Tey\Kit\Features\Core\Data\ActivityEntryData`, which serializes to the `ActivityEntry` type:

```php
use Tey\Kit\Features\Core\Data\ActivityEntryData;

ActivityEntryData::text($event->id, 'Invoice 42 sent', occurredAt: $event->at->toIso8601String(), intent: 'success',
    meta: [['label' => 'Amount', 'value' => '£120.00']], day: ['date' => '2026-10-29', 'label' => 'Today']);

ActivityEntryData::trustedHtml($event->id, '<strong>'.e($actor).'</strong> sent '.e($invoice->number), occurredAt: $at);

ActivityEntryData::group($id, ['text' => '12 reminders sent'], $firstTen, count: 12); // truncated: "and 2 more, not shown"
```

- **`headline`** is exactly one of `{ text }` or `{ html }`. Text renders as text. HTML renders unchanged through the stream's one trusted path (`trustedHtml()`): **the host's server must escape every value it puts into it** (`e()`), because the kit neither escapes nor sanitizes it. Send text unless the line needs markup.
- **`occurredAt`** (ISO 8601) goes in a `<time datetime>`. `timestamp` and `timestampTitle` are display strings the server formats (a relative time, the full date as the title). Without `timestamp`, the stream formats `occurredAt` in the viewer's locale and zone.
- **`intent`** is a kit tone (`success`, `warning`, `danger`, `info`, `neutral`), shown as a dot. The headline carries the meaning; the colour never does alone.
- **`meta`** is a list of `{ label, value }` facts. The value shows, and the label is read to screen readers.
- **A group** (`kind: 'group'`) has `count` (its full size), `children` and `truncated`, and opens in a kit Collapsible.
- **`day`** is `{ date, label }`. The server decides the label ("Today", "Monday 27 October") in the viewer's zone.

The constructor refuses a headline with both kinds or neither, an unknown tone or kind, and meta that isn't `{label, value}` strings.

## Paging: useCursorFeed

```ts
const feed = useCursorFeed<ActivityEntry>({ prop: 'feed', cursorKey: 'next_cursor', syncKey: 'sync_token' });
```

```vue
<ActivityStream :entries="feed.entries.value" :more="feed.more.value" :loading="feed.loading.value" @load-more="feed.loadMore" />
<p v-if="feed.resynced.value" role="status">History changed while you were reading, so the feed went back to the top.</p>
```

The page prop (here `feed`) holds `{ entries, next_cursor, sync_token }`. `loadMore()` makes an Inertia partial reload of only that prop, with `?cursor=` (rename it with `param`), and keeps the URL, so a reload or a shared link starts at the head. The rules:

- Pages accumulate. An entry already held isn't added twice.
- Only a null cursor ends the feed. An empty page that carries a cursor is followed straight away.
- A changed sync token means the history was rewritten. The held entries are dropped, the head page is read again, and `resynced` turns true.
- A failed request stops loading and keeps what's held, so Load more can try again.

The server reads the cursor, returns one page, and sends the same sync token for as long as earlier pages stay valid.

`createCursorFeed` is the same rules without Vue or Inertia, for a host that fetches pages its own way.

Tailwind utilities and the kit's tokens only.

## Coverage

- `composer test:js` (`activity.test.mjs`) runs the day sections, the trusted-HTML path and the cursor paging (accumulating, an empty page with a cursor, a resync on a changed token, a failed request) without Vue. It also checks by source the stream's empty state, the one `v-html`, the live-region opt-in and the composable's partial reload.
- `composer test:dashboard` covers `ActivityEntryData` (serialization, groups, the refusals) and that the dashboard widget escapes an actor's name.
- `bin/typecheck-components.sh` compiles a host's use of both (`typecheck/fixtures/ActivityFeed.vue`), including that a headline can't be both text and html.

Not covered:
- No browser renders the stream, so the focus move after Load more and the Collapsible groups aren't exercised.
- `useCursorFeed`'s wiring to Inertia's `usePage` and `router` is checked by source only.

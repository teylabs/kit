# CollectionSection and CollectionCard

`<CollectionSection>` is the section a collection of items sits in: a list, a grid, a feed or table rows. The server decides everything around the items in one descriptor, `CollectionSectionData`, and the items are the default slot. It draws the linked title, the header actions and filters, the items, the empty state, the "All <noun> →" footer and an optional scroll. It draws no card.

`<CollectionCard>` is the same section on a kit `Card`. It takes the same descriptor, props and slots, and adds only the card: its surface, its padding, the rules under the header and over the footer, and a body that fills the card so the footer stays at the bottom while the items scroll. Nothing the section shows or does is repeated in it, so switching between the two is only the name.

## Which one

- **A collection inside a card → `CollectionCard`.** A list of rows, such as a list of ongoing items, a feed or a short table. Every dashboard widget is one (`WidgetCard`), and so is the Security page's log.
- **A bare grid or list → `CollectionSection`.** Items that carry their own look, such as a grid of note cards or project cards. The header sits above them and the footer's "All <noun> →" under them, at the bottom right, with no card around them.

```vue
<!-- A list of rows, in a card -->
<CollectionCard :section="ongoing" :level="2">
    <ul class="grid divide-y divide-border">
        <li v-for="item in cases" :key="item.id" class="py-2 text-sm"><Link :href="item.url">{{ item.title }}</Link></li>
    </ul>
</CollectionCard>

<!-- A grid of cards, bare -->
<CollectionSection :section="notes" :level="2">
    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <li v-for="note in items" :key="note.id"><NoteCard :note="note" /></li>
    </ul>
</CollectionSection>
```

## How they fit together

CollectionSection is the real component; CollectionCard is a thin wrapper over it. It is the dashboard's `WidgetCard` made general, not a parallel component: `WidgetCard` is now a thin adapter that builds a `CollectionSectionData` from a widget's frame and renders a CollectionCard. It is not a `PageSection` either: a PageSection is any titled region of a page, whose header always sits outside and which draws a card only on a tinted ground; a CollectionSection is driven by its descriptor. For a card that is not a collection (one record, one decision, a summary band), use the card parts in [docs/cards.md](cards.md).

## Example

```php
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\CollectionSectionData;
use Tey\Kit\Features\Core\Data\CollectionSegment;
use Tey\Kit\Features\Core\Data\CollectionSortData;
use Tey\Kit\Features\Core\Data\SeeAll;
use Tey\Kit\Features\Core\Lists\ListEmptyData;

public function ongoing(): CollectionSectionData
{
    return new CollectionSectionData(
        title: 'Ongoing cases',
        description: 'Cases someone is working on.',
        state: $this->cases === [] ? 'empty' : 'ready',
        empty: new ListEmptyData('first-use', 'Nothing ongoing', 'Cases someone picks up show up here.'),
        actions: [new Action('New case', route('cases.create'), 'get')],
        segments: [
            new CollectionSegment('all', 'All', route('dashboard'), current: $this->segment === 'all', count: 30),
            new CollectionSegment('new', 'New', route('dashboard', ['segment' => 'new']), current: $this->segment === 'new', count: 4),
            new CollectionSegment('progress', 'In progress', route('dashboard', ['segment' => 'progress']), current: $this->segment === 'progress', count: 26),
        ],
        sortBy: new CollectionSortData([['key' => 'touched', 'label' => 'Last touched'], ['key' => 'due', 'label' => 'Due soonest']], $this->sort, route('dashboard'), reload: ['ongoing', 'cases']),
        seeAll: SeeAll::to('All ongoing cases', route('cases.index', ['status' => 'ongoing'])),
        maxHeight: '24rem',
    );
}
```

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CollectionCard, type CollectionSectionData } from '@kit/ui';

defineProps<{ ongoing: CollectionSectionData; cases: { id: number; title: string; url: string }[] }>();
</script>
<template>
    <CollectionCard :section="ongoing" :level="2">
        <ul class="grid divide-y divide-border">
            <li v-for="item in cases" :key="item.id" class="py-2 text-sm"><Link :href="item.url">{{ item.title }}</Link></li>
        </ul>
    </CollectionCard>
</template>
```

As a CollectionCard, that draws:

```
Ongoing cases                               Sort by [Last touched ⌄]  New case
Cases someone is working on.
──────────────────────────────────────────────────────────────────────────────
(All 30) (New 4) (In progress 26)
──────────────────────────────────────────────────────────────────────────────
Case 101
Case 102                                                  (scrolls past 24rem)
…
──────────────────────────────────────────────────────────────────────────────
                                                         All ongoing cases →
```

## Its parts

| Part | From | What it does |
| --- | --- | --- |
| Title | `title`, `description` | A heading at `level` (3 by default; the page decides the hierarchy), with `description` under it. With a `seeAll` the title links to the same URL, heading-coloured and underlined on hover, unless the see-all says `->withoutTitleLink()`. It names the card (`aria-labelledby`). |
| Header actions | `actions`, `#actions` | On the header's right: the Sort by menu, then the `#actions` slot, then the descriptor's `Action`s. Plain GET navigation is a compact link. Every other action (a `confirm`, `disabled`, a `dialog`, `panel` or `document` intent, `newTab`, another method) goes to one `ActionBar` after the links, which keeps its meaning: it asks first, shows its `reason` and does nothing while disabled, opens the route modal or a new tab. A host that listens to the section's `action` event runs those itself (a `panel` needs it); otherwise the bar runs them. Plain navigation to the see-all's URL is left out, so that link is drawn once; an action to the same URL that does more (a new tab, a dialog) stays. |
| Segment tabs | `segments` | Below the header: links to the same page showing each segment ("All", "New", "In progress"), in a navigation named "<title> views". The current one is `aria-current="page"` and drawn filled; one with a `count` shows it and is named "New (4)". At most one is current. |
| Filters | `#filters` | Beside the segment tabs, for anything else that narrows the items. |
| Sort by | `sortBy` | A labelled `InputSelect`. Choosing an order visits `url` with `{ sort }` by `method` (`get` for a page that reads `?sort=`, `put` to save it for the viewer), reloading only the `reload` props; a refusal puts the old order back. Shown only while ready. |
| Items | default slot | Anything: a list, a grid, a feed, table rows. Remounted when the order changes. |
| Empty state | `state: 'empty'`, `empty`, `#empty` | In place of the items: the descriptor's title, description and optional action (a link when it is plain navigation, otherwise an `ActionBar` with its meaning kept, as in the header), or the `#empty` slot (it receives `{ empty }`). With neither, the body is left blank: an empty section never shows its items. The see-all stays: the whole list may hold rows the viewer can filter to. |
| Error | `state: 'error'`, `error` | In place of the items: the message and Retry (a reload). The segment tabs, the actions and the see-all are hidden, and the title is plain text. |
| Scroll | `maxHeight` | A `px`, `rem` or `em` length past which the items scroll inside the section. The scrolling body is a focusable region named "<title> items", so a keyboard can scroll it. |
| Footer | `seeAll`, `#footer` | At the bottom right, outside the scroll, so it stays in view: the see-all, "All ongoing cases →" ([docs/see-all.md](see-all.md)). `#footer` puts a muted line to its left. A see-all with `->inHeader()` goes last in the header instead, for a card that is not a list. |

The focus order is the title, the header's controls, the segment tabs, the items, then the see-all.

Props (the same on both) besides `section`: `level`, `headless` (no title bar; the section keeps its title as its accessible name) and `minHeight` (the ready body's least height, while deferred content loads; a widget's `loadingHeight`).

## The descriptor

`Tey\Kit\Features\Core\Data\CollectionSectionData` (a `Data` class, `#[TypeScript]`):

| Field | Meaning |
| --- | --- |
| `title` | Required, never blank. |
| `description` | Optional line under the title. |
| `state` | `ready` (the default), `empty` or `error`. |
| `empty` | A `ListEmptyData`, drawn when `state` is `empty`. |
| `error` | A `ListErrorData`, drawn when `state` is `error`. |
| `actions` | `Action`s for the header. |
| `segments` | `CollectionSegment`s: `key`, `label`, `url`, `current`, an optional `count`. |
| `sortBy` | A `CollectionSortData`: `options` (`key`, `label`), `current`, `url`, `method` (`get`), `reload` (`[]`, the whole page), `label` ("Sort by"). The same shape as a widget's `sortBy`. |
| `seeAll` | A `SeeAll`, or null. |
| `maxHeight` | A `px`, `rem` or `em` length, or null for no scroll. |

A blank title, another state, more than one current segment, or a `maxHeight` that is not a plain `px`, `rem` or `em` length throws `InvalidArgumentException('collection_section_invalid')`: a mistake is never drawn. On the client it is the `CollectionSectionData` type from `@kit/ui`, with `CollectionSegment`, `CollectionSort` and `CollectionAction`.

## Where the kit uses it

| Where | How |
| --- | --- |
| Dashboard widgets | `Partials/WidgetCard.vue` builds the descriptor from the widget's frame and renders a CollectionCard (title, state, empty, error, actions, `sortBy`, `seeAll`) and renders the widget's body as its items, with `loadingHeight` as `minHeight`. Every kit widget draws through it: the notes widget, People, Recent activity, "Set up this workspace", the You card (`headless`), and the error card of a widget that failed. |
| Security log | `SecurityViewModel::log()` is the log's descriptor: "Security log", what it holds, and "Nothing yet" as its empty state. The page renders `<CollectionCard :section="log" :level="2">` around the events. |

## Coverage

`composer test:dashboard` covers the descriptor: its serialization with segments (one current, counts kept or null), a sort menu, a see-all and a max height, and its refusals: a blank title, an unknown state, two current segments, and a `calc()` or `%` max height. `composer test:passkeys` covers the Security page's `log`: its title, its state following the events, and its empty state's words. The browser job (`?collection-section`, `&dark`) covers, on CollectionCards: the title as a link to the see-all's URL, plain with `linkTitle: false`, in the heading's colour; the segment tabs as a navigation named "Ongoing cases views" with one link per segment, only the current one `aria-current`, named with their counts, between the header and the items; Sort by by keyboard, making one visit with the chosen order; the descriptor's empty state and the `#empty` slot in place of the items, with the see-all still in the footer; with `maxHeight`, the items scrolling inside a focusable region named "Ongoing cases items" while the footer's see-all stays in view and in place, with the page not scrolling; and the see-all in the footer for a list and in the header with its count for a stat card, axe in light and dark. A bare CollectionSection: no border, fill or shadow (the CollectionCards have them), no rules, six project cards three to a row, the title linked and the header action above them, one link named "All active projects" under the grid, after it, outside every card and flush with its right edge, its arrow hidden, the keyboard order title, header action, each card, then the see-all, and axe in light and dark; and an empty one with its empty state in place of the grid and its see-all still under it. One descriptor in both (`&twin=section` and `&twin=card`, light and dark): inside the card, the section has the same elements, attributes, roles, names, links and text, and the same accessibility tree, as the bare one; the card holds the section and nothing else, with a border, fill and rounded corners; the bare section has no border, fill, padding or rules, and on the card its header, body and footer take 16px of padding with a rule under the header and over the footer; both link the title and have one see-all, and pass axe. It fails when CollectionCard changes anything the section draws. On `&edge`: a plain GET header action is a link; a confirmed POST is a button that opens its Dialog and sends nothing until confirmed, then one POST; a disabled action is `aria-disabled` with its reason and sends nothing; a new-tab action to the see-all's URL stays, opening a new tab; an empty state's POST action is a button that makes its POST; an error card's title is plain text with no see-all; an empty card with no copy and no slot shows none of its items and keeps its see-all; axe passes. Each fails with its fix undone. The dashboard's see-all, title-link and keyboard tests run through `WidgetCard`, and so through CollectionCard. Not covered: a consumer app, and browsers other than Chromium.

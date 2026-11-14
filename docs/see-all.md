# See-all links: SeeAll

A list section or widget that shows the first few of something links to the whole list with one "see all" link. The server decides it, as a `SeeAll` descriptor, and the kit draws every one the same way: the noun, then an arrow.

```
All cases →
All cases (42) →
```

An app never writes the link itself. It returns the descriptor from its view model or widget, and the section it renders in, a [CollectionSection](collection-section.md), puts it in place, on a card or under a grid of cards. The section's title links to the same place too.

```php
use Tey\Kit\Features\Core\Data\SeeAll;

SeeAll::to('All cases', route('cases.index'));                        // in the footer
SeeAll::to('All cases', route('cases.index'), count: $open);          // "All cases (42) →"
SeeAll::to('All cases', route('cases.index'))->inHeader();            // in the header, for a card that is not a list
SeeAll::to('All cases', route('cases.index'))->withoutTitleLink();    // the card's title stays plain text
```

## Where the URL goes

The URL lands on the index **filtered to match the card**, so the reader finds the same rows they were looking at, and the rest of them. An "Ongoing cases" card links to the cases index filtered to ongoing, not to the unfiltered list; a "Due this week" card to the index filtered to this week; a "My open requests" card to the index filtered to the viewer and open. Name the label the same way: "All ongoing cases".

```php
SeeAll::to('All ongoing cases', route('cases.index', ['filter' => ['status' => 'ongoing']]));
```

A card that already shows the whole of something (the latest notes, newest first) links to the unfiltered index.

## The title

With a see-all, the card's title links to the same URL. A reader expects the heading to open what it names, and the title link is first in the card's focus order, before its rows; the see-all is last, after them.

- **It stays a heading.** The link takes the heading's own colour, never a body link's (`text-primary`, or a colour a host gives every `a`). It is underlined only on hover, and shows a focus ring on keyboard focus, drawn inside the link so a heading that truncates does not clip it. Its name is the title ("Open cases"); the heading keeps its level and name.
- **Opt out** with `->withoutTitleLink()` (sent as `linkTitle: false`) for the rare card whose title should not link: a stat card whose header link sits right beside its title, say, or a title that names something other than the list.
- A card with no see-all keeps a plain title.

## The label

Name the noun: "All cases", "All notes", "All open invoices". Never "View all", "See all" or "Show all": the label alone must say where the link goes, because a screen reader's list of links reads it without the card around it. The accessible name is the label, with the count when there is one ("All cases (42)"); the arrow is an icon hidden from assistive tech.

## The descriptor

`Tey\Kit\Features\Core\Data\SeeAll` (a `Data` class, `#[TypeScript]`) serializes to:

| Field | Meaning |
| --- | --- |
| `label` | The words: "All cases". Required, never blank. |
| `url` | Where it goes. Required, never blank. |
| `count` | Optional. Drawn in brackets after the label: "All cases (42) →". Zero is shown; null is not. Never negative. |
| `placement` | `footer` (the default) or `header`. |
| `linkTitle` | Whether the card's title links to `url` too: true (the default), false with `withoutTitleLink()`. |

- **`SeeAll::to($label, $url, ?$count)`** makes one in the footer.
- **`->withCount(?int)`**, **`->inHeader()`** and **`->withoutTitleLink()`** each return a new descriptor; the original is unchanged.
- A blank label or URL, a negative count or another placement throws `InvalidArgumentException('see_all_invalid')`: a mistake is never drawn.

On the client it is the `SeeAll` type from `@kit/ui` (`{ label, url, count?, placement?, linkTitle? }`).

## Where it goes

- **Lists: the footer** (the default). A list section or list widget draws it as a footer at the card's bottom right, after the list's end. It is always there while the card shows its content, and it sits outside the list's own scroll, so it stays in view while the list scrolls inside the card.
- **Everything else: the header.** A summary, a stat or a chart has no list end to sit after, so `->inHeader()` puts the same link, with the same label and arrow, last in the card's header, after its actions.

A card in an error state shows no see-all. A card that is empty still does: the whole list may have other rows the viewer can filter to.

## Where the kit draws it

| Where | How |
| --- | --- |
| `CollectionSection` | The section a collection sits in ([docs/collection-section.md](collection-section.md)): `seeAll` in its `CollectionSectionData` draws a footer one at the bottom right, outside the body's scroll (on a card) or under the grid of cards (`surface="none"`), or a header one after the header's actions, and links the card's title. |
| Dashboard widgets | `Widget::seeAll(): ?SeeAll` is part of the widget's frame (`seeAll`). `WidgetCard` passes it to its CollectionSection. |
| Security log | `SecurityViewModel::log()` is a CollectionSectionData with no see-all: the log is the whole of it. |
| `SearchBox` | Its results end with "All results →", kept in view at the bottom of the list while it scrolls; it goes to the full results page, as Enter with nothing chosen does. |
| `SeeAllLink` | The link itself, for an app's own card: `<SeeAllLink :see-all="widget.seeAll" />`. It places nothing. `variant="title"` is the title link, with the title as its default slot: `<h2><SeeAllLink :see-all="seeAll" variant="title">{{ title }}</SeeAllLink></h2>` (check `seeAll.linkTitle !== false` first). |

The notes widget (`notes.recent`) is a list, so its "All notes" is a see-all in the footer, and its "Notes" title links to the notes wall. The widget shows the latest notes, so the wall unfiltered is the match.

## The deprecated way: an "All …" header action

Before the descriptor, a widget offered its see-all as a header action, such as `new Action('All notes', $url, 'get')`. That still works, and maps onto the descriptor:

- A widget with no `seeAll()` whose `actions()` include a plain link labelled "All …", "View all …", "See all …" or "Show all …" (a GET, enabled, unconfirmed, not destructive, not in a menu) gets the first one as its `seeAll`, in the footer. "View all cases" becomes "All cases". A bare "View all" names no noun and stays an action.
- The other way round, a widget with a `seeAll()` also sends it as a header action (key `see-all`), unless one of its GET actions already has that URL. So a `WidgetCard` an app copied before the descriptor still draws the link, in its header as before. The current `WidgetCard` leaves out the GET action whose URL is the see-all's, so the link is drawn once.

Move a widget to `seeAll()` when you next touch it: the alias stays for now, and will be removed in a later release.

## Coverage

`composer test:dashboard` covers the descriptor: its serialization (`toArray()` and JSON), `withCount()` and `inHeader()` leaving the original unchanged, the four refusals, the alias's mapping and what stays an action, and the frames of a list widget, a stat widget and a widget on the alias; and `withoutTitleLink()` serializing `linkTitle` false and surviving `withCount()` and `inHeader()`. `composer test:notes` covers the notes widget's `seeAll` and its header-action twin. The browser job covers, on the dashboard (`dashboard.html?see-all`): one link named "All cases" in a list card, in the footer at the bottom right after the list's end, its arrow hidden, axe in light and dark; the link in the same place, on top and in view while the list scrolls inside the card, with the page not scrolling; a stat card's "All cases (42)" in its header after the title; the keyboard order: the title link, the list and its rows, then the see-all, before the next card, with a focus ring, and Shift+Tab back; the title as a link inside the heading with the see-all's href, and plain text with `linkTitle: false`; and, with a host stylesheet colouring every `a` and `--primary` set apart, that the title link's computed colour is the heading's, not the host's link colour or `text-primary`'s, at rest and focused, underlined only on hover, with a focus ring that stays inside the heading, light and dark. On the components page (`?collection-section`): CollectionSection's footer and header placements, the footer under a grid of cards with `surface="none"`, axe in light and dark, the title link (plain with `linkTitle: false`) and its colour, and SearchBox's "All results" in view while its results scroll, visiting the full results page. Not covered: a consumer app, and browsers other than Chromium.

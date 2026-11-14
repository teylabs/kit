# See-all links: SeeAll

A list section or widget that shows the first few of something links to the whole list with one "see all" link. The server decides it, as a `SeeAll` descriptor, and the kit draws every one the same way: the noun, then an arrow.

```
All cases →
All cases (42) →
```

An app never writes the link itself. It returns the descriptor from its view model or widget, and the card it renders in puts it in place.

```php
use Tey\Kit\Features\Core\Data\SeeAll;

SeeAll::to('All cases', route('cases.index'));                        // in the footer
SeeAll::to('All cases', route('cases.index'), count: $open);          // "All cases (42) →"
SeeAll::to('All cases', route('cases.index'))->inHeader();            // in the header, for a card that is not a list
```

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

- **`SeeAll::to($label, $url, ?$count)`** makes one in the footer.
- **`->withCount(?int)`** and **`->inHeader()`** each return a new descriptor; the original is unchanged.
- A blank label or URL, a negative count or another placement throws `InvalidArgumentException('see_all_invalid')`: a mistake is never drawn.

On the client it is the `SeeAll` type from `@kit/ui` (`{ label, url, count?, placement? }`).

## Where it goes

- **Lists: the footer** (the default). A list section or list widget draws it as a footer at the card's bottom right, after the list's end. It is always there while the card shows its content, and it sits outside the list's own scroll, so it stays in view while the list scrolls inside the card.
- **Everything else: the header.** A summary, a stat or a chart has no list end to sit after, so `->inHeader()` puts the same link, with the same label and arrow, last in the card's header, after its actions.

A card in an error state shows no see-all. A card that is empty still does: the whole list may have other rows the viewer can filter to.

## Where the kit draws it

| Where | How |
| --- | --- |
| Dashboard widgets | `Widget::seeAll(): ?SeeAll` is part of the widget's frame (`seeAll`). `WidgetCard` draws a footer one under the body, outside the body's ScrollList, and a header one after the header's actions. |
| `PageSection` | The `seeAll` prop: a footer one after the content, at the right (inside the ground card when one is drawn), or a header one in its `SectionHeader`. A section with no title has no header, so a header one stays in the footer. |
| `SectionHeader` | The `seeAll` prop: drawn only with `placement: 'header'`, after the actions. A footer one is the wrapper's to draw. |
| `SummaryCard` | The `seeAll` prop: at the right of the sticky footer, after anything in `#footer`, or after the header's `#actions`. |
| `SearchBox` | Its results end with "All results →", kept in view at the bottom of the list while it scrolls; it goes to the full results page, as Enter with nothing chosen does. |
| `SeeAllLink` | The link itself, for an app's own card: `<SeeAllLink :see-all="widget.seeAll" />`. It places nothing. |

The notes widget (`notes.recent`) is a list, so its "All notes" is a see-all in the footer.

## The deprecated way: an "All …" header action

Before the descriptor, a widget offered its see-all as a header action, such as `new Action('All notes', $url, 'get')`. That still works, and maps onto the descriptor:

- A widget with no `seeAll()` whose `actions()` include a plain link labelled "All …", "View all …", "See all …" or "Show all …" (a GET, enabled, unconfirmed, not destructive, not in a menu) gets the first one as its `seeAll`, in the footer. "View all cases" becomes "All cases". A bare "View all" names no noun and stays an action.
- The other way round, a widget with a `seeAll()` also sends it as a header action (key `see-all`), unless one of its GET actions already has that URL. So a `WidgetCard` an app copied before the descriptor still draws the link, in its header as before. The current `WidgetCard` leaves out the GET action whose URL is the see-all's, so the link is drawn once.

Move a widget to `seeAll()` when you next touch it: the alias stays for now, and will be removed in a later release.

## Coverage

`composer test:dashboard` covers the descriptor: its serialization (`toArray()` and JSON), `withCount()` and `inHeader()` leaving the original unchanged, the four refusals, the alias's mapping and what stays an action, and the frames of a list widget, a stat widget and a widget on the alias. `composer test:notes` covers the notes widget's `seeAll` and its header-action twin. The browser job covers, on the dashboard (`dashboard.html?see-all`): one link named "All cases" in a list card, in the footer at the bottom right after the list's end, its arrow hidden, axe in light and dark; the link in the same place, on top and in view while the list scrolls inside the card, with the page not scrolling; a stat card's "All cases (42)" in its header after the title; and the keyboard order: Tab reaches the link straight after its list, before the next card, with a focus ring, and Shift+Tab goes back. On the components page (`?see-all`): PageSection's footer and header placements and SummaryCard's footer, axe in light and dark; and SearchBox's "All results" in view while its results scroll, visiting the full results page. Not covered: a consumer app, and browsers other than Chromium.

# Cards

A card is a `Surface` in parts. Use them instead of a hand-rolled bordered `div`.

| Part | What it is |
| --- | --- |
| `Card` | The frame: a Surface with no padding, its parts divided by a rule. A `section` by default, so its header's heading names it. |
| `CardHeader` | The title as a real heading (`level`, 3 by default), from `title` or the default slot. A `#meta` line under it; `#avatar` and `#leading` (a drag grip) before it; `#status` and `#actions` on the other side. |
| `CardBody` | The content, in the card's gutters. |
| `CardFooter` | A compact footer; `sticky` keeps it at the bottom of a scrolling card. |
| `CardBanner` | A short centred band at the top that draws the eye. Its words carry the meaning, never the colour alone. |
| `RecordCard` | One record: a header with `#meta` and `#actions`, details in the body, a muted `#footer`. |
| `ActionCard` | A plain panel with one decision in it, no header band: a `title` and `description` (or `#description`, capped at `max-w-xl`) on the left, `#actions` on the right (centred, or top-right with `align="top"`), `tone="danger"`, more below in the default slot. |
| `SummaryCard` | A card with a header band: a muted header with an optional `#icon` and `#actions`, the content (or `#body`), a sticky `#footer`. With an empty default slot and no `#body` it has no body. `seeAll` draws the server's "All cases →" link at the right of the sticky footer, or after `#actions` with `placement: 'header'` ([docs/see-all.md](see-all.md)). |

> **v0.16 swapped these two names** to match the apps they came from. Before v0.16, `ActionCard` was the header-band card and `SummaryCard` the panel. See [Upgrading to 0.16](#upgrading-to-016).

```vue
<RecordCard :title="comparable.address">
    <template #meta><a :href="comparable.source.url">{{ comparable.source.name }}</a> · {{ comparable.seller }}</template>
    <template #actions>
        <ButtonGroup>
            <InertiaAction :action="comparable.actions.view" />
            <template #menu><DropdownAction :action="comparable.actions.edit" /></template>
        </ButtonGroup>
    </template>
    <ComparableDetails :comparable="comparable" />
</RecordCard>
```

- **Actions** are written out by name in `#actions` (docs/single-actions.md).
- **Tone:** `CardHeader` and `CardBanner` take `tone`. `primary` is a solid fill, `danger` a tinted band; `success`, `warning`, `info` and `muted` are a muted band with toned text.
- **`unstyled`** on `CardHeader` drops its padding and type so your classes rule; every slot still works.
- **In a Repeater**, pass a `CardHeader` (`unstyled`) through the `row-header` slot; the Repeater keeps the grip.
- `SectionHeader` stays the header for a section, widget or pane outside a card.

## ActionCard

```vue
<ActionCard title="Delete workspace" description="Removes every project and member. This cannot be undone." tone="danger">
    <template #actions><Button variant="secondary" class="text-destructive-text" @click="confirmDelete">Delete workspace</Button></template>
</ActionCard>
```

- **Parts:** it is a `Card` with a `CardHeader` (no tone) and a `CardBody`.
- **Layout:** the title (a heading at `level`, 3 by default) and description on the left, the description capped at `max-w-xl`; `#actions` on the right, centred on the text. `align="top"` pins them top-right instead. On a phone the actions stack under the text.
- **Below:** the default slot renders under the text and actions. A slot that renders nothing (an empty `#actions`, a false `v-if`) leaves no wrapper or gap.
- **`tone="danger"`:** a red edge and a red title (`text-destructive-text`). The card keeps its background, with no tint, so the description and the actions keep the contrast they have on any card, in light and dark. Put a quiet button with red text in `#actions` and keep the filled red for the confirm dialog.

## Upgrading to 0.16

v0.16 swaps the two names. The props, slots and look of each card are unchanged; only the name you import changes.

| Before v0.16 | From v0.16 | What it is |
| --- | --- | --- |
| `ActionCard` | `SummaryCard` | The header-band card: muted header, `#icon`, `#actions`, `#body`, sticky `#footer` |
| `SummaryCard` | `ActionCard` | The plain panel: `title`, `#description`, `#actions`, default slot below |

1. Get core's renamed cards into the app on 0.16 or 0.17: since 0.18 the kit writes no files into an app.
2. Find each `.vue` file under `resources/js` that still uses an old name: an `<ActionCard>` with `#icon`, `#body`, `#footer` or an `editUrl`, or a `<SummaryCard>` with `#description`. Since 0.18 no kit command lists them.
3. In each such file, rename the tag and its import: old `ActionCard` becomes `SummaryCard`, and old `SummaryCard` becomes `ActionCard`. Do it in one pass, for example by renaming the old `ActionCard` to a placeholder first, so the two renames don't overwrite each other.
4. Coming from an app where ActionCard already meant the panel, keep `ActionCard`. It is that panel now, with `tone="danger"` and `align` added.
5. Search again until no file uses an old name.

A search on slots and props cannot catch a card that uses only `title` and the default slot. Check those by eye.

Tests: the parts are typechecked with the other components. `tests/js/card-names.test.mjs` covers both cards' API and SummaryCard's empty body; the browser suite (`?card-names`, light and dark, 1280 and 390px) covers ActionCard's layout, its phone stack, SummaryCard's empty body and axe with colour contrast on the danger tone. The other parts have no rendered test yet.

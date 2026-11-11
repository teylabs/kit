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
| `SummaryCard` | A summary with a call to action: a title and `#description`, with `#actions` beside them. |
| `ActionCard` | A panel of one thing: a muted header with an optional `#icon` and `#actions`, the content (or `#body`), a sticky `#footer`. With an empty default slot and no `#body` it has no body. |
| `ActionPanel` | A plain panel with one decision in it, no header band: a `title` and `description` (or `#description`, capped at `max-w-xl`) on the left, `#actions` on the right, more below in the default slot. |

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

## ActionPanel

```vue
<ActionPanel title="Delete workspace" description="Removes every project and member. This cannot be undone." tone="danger">
    <template #actions><Button variant="secondary" class="text-destructive-text" @click="confirmDelete">Delete workspace</Button></template>
</ActionPanel>
```

- **Naming:** coming from apps where ActionCard meant the panel: use ActionPanel (or SummaryCard). The kit's ActionCard is the header-band card.
- **Parts:** it is a `Card` with a `CardHeader` (no tone) and a `CardBody`, like `SummaryCard`; it adds `tone`, `align` and the empty-slot handling.
- **Layout:** the title (a heading at `level`, 3 by default) and description on the left, the description capped at `max-w-xl`; `#actions` on the right, centred on the text. `align="top"` pins them top-right instead. On a phone the actions wrap under the text.
- **Below:** the default slot renders under the text and actions. A slot that renders nothing (an empty `#actions`, a false `v-if`) leaves no wrapper or gap.
- **`tone="danger"`:** a red edge and a red title (`text-destructive-text`). The panel keeps the card background, with no tint, so the description and the actions keep the contrast they have on any card, in light and dark. Put a quiet button with red text in `#actions` and keep the filled red for the confirm dialog.

Tests: the parts are typechecked with the other components. `tests/js/action-panel.test.mjs` covers ActionPanel's API and ActionCard's empty body; the browser suite (`?action-panel`, light and dark, 1280 and 390px) covers ActionPanel's layout, its phone stack, ActionCard's empty body and axe with colour contrast on the danger tone. The other parts have no rendered test yet.

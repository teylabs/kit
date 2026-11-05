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
| `ActionCard` | A panel of one thing: a muted header with an optional `#icon` and `#actions`, the content (or `#body`), a sticky `#footer`. |

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

Tests: the parts are typechecked with the other components. No rendered test yet.

# InlineDropdown

A link-styled trigger that opens a menu of choices, one of them current: an optional icon, the current choice's label in medium weight, then a chevron. It has no field border, so it sits in a header or a sentence ("⇅ Due soonest ⌄"). Use it for a short, fixed set of choices that change what a page or section shows: a sort order, a view, and later a breadcrumb's siblings. A form value stays an `InputSelect` or a radio group.

```vue
<InlineDropdown
    v-model="order"
    :items="[
        { key: 'touched', label: 'Last touched' },
        { key: 'due', label: 'Due soonest' },
    ]"
    label="Sort by"
    :subject="{ title: 'Sort by', caption: 'Ongoing cases' }"
    :icon="ArrowDownUp"
    align="end"
    @select="visitWith"
/>
```

| Prop | What it does |
| --- | --- |
| `items` | `InlineDropdownItem[]`: `key`, `label`, and optionally `href` (the item is a link: an Inertia `Link`, or a plain anchor with `external`), `disabled` and `section` (runs of items with the same `section` are a labelled group, as in the kit's other menus). |
| `modelValue` | The current item's key. It shows on the trigger and is checked in the menu. |
| `label` | The trigger's accessible name before the current label, "Sort by: Due soonest". Not shown. |
| `placeholder` | The trigger's text when no item is current. |
| `icon` | A Lucide icon before the label, muted. |
| `subject` | The menu's subject header (README, "Menu subject"): a `MenuSubject`, or a string for the title alone. |
| `align` | `start` (default) or `end`; the menu flips to the edge that fits. |
| `disabled` | The trigger does nothing (say, while a choice is saving). |

The items are `menuitemradio` rows in one radio group, the current one `aria-checked` with a check mark. Choosing an item without `href` emits `update:modelValue` and `select` with its key; choosing the current item emits nothing. An item with `href` navigates, and emits nothing. Caller attributes land on the trigger `<button>`.

CollectionSection's (and so CollectionCard's and a dashboard widget's) Sort by is an InlineDropdown: the sort icon, the current order, and the menu headed by the descriptor's label over the section's title.

## Checks

The browser job (`?inline-dropdown`, `&dark`, at 1280 and 390px) covers the trigger's name ("Sort by: Last touched"), the menu's subject header, the radio rows with the current one checked, axe with contrast while open, choosing the current item emitting nothing and a new one emitting its key once, and link items as anchors with their `href` in labelled groups. `KIT_INLINE_DROPDOWN_SHOTS=<dir>` saves each scheme and width with the menu open. The dashboard and CollectionSection Sort by tests drive it by keyboard and pointer. Not covered: a consumer app, and browsers other than Chromium.

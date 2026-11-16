# Widgets and segment links

## Widget

A dashboard widget frame: a `<section>` on its own card, named by its heading, with the body under a header row.

```vue
<Widget :widget="{ key: 'notes', title: 'Notes', meta: 'Last 7 days', count: notes.length, state: notes.length ? 'ready' : 'empty', empty: { title: 'No notes yet', icon: 'inbox' } }" :loading="!notes">
    <template #actions><Button variant="secondary" @click="add">Add note</Button></template>
    <NoteList :notes="notes" />
</Widget>
```

- `widget`: `{ key?, title, meta?, state?, empty?, count?, status?, headerVisible? }` (`WidgetDescriptor`). `state` is `ready` (default), `empty` (draws `empty` with the kit's EmptyState, compact and start-aligned) or `planned` (a dashed frame for a widget that is coming). `count` draws a badge beside the title; `status` (`{ label, tone }`) a ToneLabel at the header's end when there are no actions.
- `loading`: a pulsing skeleton (still under reduced motion) and `aria-busy` on the section.
- `action-placement`: `header` (default) or `footer` for the `actions` slot.
- `bare`: no card and no header, for a widget inside something that frames it already; the section is labelled by the title. Its actions go in the footer.
- `level`: the heading level (2). EmptyState's title is one level below.
- Slots: `title`, `avatar`, `meta`, `badge`, `actions`, default.
- On a tinted page ground it draws its own card, like DataTable and EmptyState.

`WidgetHeader` is its header row, exported for a frame of your own: `heading-id`, `meta`, `fill`, `level`, with slots `avatar`, default (the heading), `meta`, `badge` and `actions`.

## SegmentLinks

Filter segments that are links: each filters the list by URL, with an optional count and tone dot.

```vue
<SegmentLinks :segments="stages" :current="filters.stage" label="Filter by stage" @navigate="(event, href) => { event.preventDefault(); router.visit(href, { only: ['assignments'], preserveState: true, preserveScroll: true }); }" />
```

- `segments`: `{ key, label, href, count?, tone? }[]` (`SegmentLink`). A segment without a `tone` has no dot.
- `current`: the key of the segment shown, marked `aria-current="page"`.
- `label`: the `<nav>` landmark's name ("Filter").
- `appearance`: `link` (small link text) or `neutral` (quiet 40px chips).
- `navigate(event, href)` fires before the Link visits; call `event.preventDefault()` to make the visit yourself (a partial reload with `only`). Otherwise each segment is an Inertia Link that keeps state and scroll.

Pick SegmentedToggle for a form value or a view switch (a radiogroup), PaneTabs for panes inside one page, and PageTabs for a record's or module's pages.

# Page rhythm

A page's vertical spacing has one owner. `<Page>` puts one gap (`gap-6`) between its visible direct children: the head (trail, title, status and actions), then each block of content. Inside a block, a `<Stack>` (or a `<PageSection>`, or a `<PageGridItem>`) owns the gap between its own children. Components carry no outer margins, so nothing doubles up and nothing collapses.

```vue
<Page md>
    <Alert v-if="notice">{{ notice }}</Alert>
    <PageSection title="Profile" description="How others see you.">
        <FormCard>…</FormCard>
    </PageSection>
    <PageSection title="Danger zone">
        <FormCard>…</FormCard>
    </PageSection>
</Page>
```

## Rules

- **No outer margins on page children.** No `mt-*`, `mb-*`, `my-*` or `space-y-*` to separate blocks. To space blocks differently, nest a `<Stack class="gap-8">` (a deliberate rhythm), never a margin on one block.
- **Out-of-flow children take no gap.** The gap is a flex gap, so an absolutely or fixed positioned child, a hidden one (`hidden`, `v-show`), a closed `<dialog>` and anything a portal (`<Teleport>`) renders elsewhere add no space. Only a child that takes up room in the column is spaced. A zero-height child still in flow (an empty `<div>`) does take a gap: render it with `v-if`, or position it out of flow.
- **`<Page flush>` turns the gap off,** for a page whose content is one full-bleed child that lays itself out: a Workbench, a SplitPane, a canvas. A flush page holds that one child; a page with several blocks keeps the gap.

## Compact auth cards

The guest pages (Login, Register, ForgotPassword, ResetPassword, ConfirmPassword, TwoFactor, VerifyEmail), the invitation page and the mcp consent page are compact cards (`<Page xs>`), and they keep the page's `gap-6` between the heading and the form. Where two blocks belong together, a card groups them in a `<Stack>` with a tighter gap instead of a margin:

- a heading and its flashed status (Login, Register): `<Stack>` (`gap-4`);
- the consent request and its scopes: `<Stack>` (`gap-4`); Approve and Deny, one decision: `<Stack class="gap-3">`.

This is the one deliberate exception to the page's rhythm in the kit's own pages. A host's own compact card follows the same rule: group with a Stack, never a margin.

## Stack

`<Stack>` stacks its children vertically with `gap-4` (`flex flex-col`). Pass `class="gap-6"` (or another gap) for a deliberate different rhythm, `as="section"` for its element, or `as-child`. It is vertical only: a row of controls is a `<Toolbar>`, and page columns are a `<PageGrid>`.

`BlockStack` is the old name. It still renders a `Stack` (with a development warning) for one release, then it is removed.

## PageSection

`<PageSection>` is one titled region of a page: a `<section>` named by its `title`, with a SectionHeader (`title`, `description`, descriptor `actions`, heading `level`, 2 by default) above its content, stacked with `gap-4`. With no `title` it is a plain stacked group (pass `aria-label` to name it). It emits `action` as SectionHeader does.

A PageSection's title is never the page's title: the page's heading is `<Page>`'s.

## PageGrid

`<PageGrid>` lays page-level blocks out in 12 columns from `md`, and one column (in source order) below it. The gap is the page's (`gap-6`). Cells shrink below their content (`min-w-0`), so a wide table scrolls inside its cell.

| Prop | | |
|---|---|---|
| `preset` | `main-aside` | The first cell 8 columns, the rest 4. |
| | `main-aside-wide` | The first cell 7 columns, the rest 5: a wider aside (about 58/42). |
| | `halves` | 6 columns each. |
| | `thirds` | 4 columns each. |
| | none | Each cell spans all 12 unless it is a `<PageGridItem span>`. |

`<PageGridItem span="…">` (1 to 12) sets one cell's columns from `md`, over the preset. It stacks its own content with `gap-4`, and takes `as` or `as-child`.

```vue
<Page>
    <PageGrid preset="main-aside">
        <PageGridItem><DataTable :table="table" /></PageGridItem>
        <PageGridItem><PageSection title="Filters">…</PageSection></PageGridItem>
    </PageGrid>
</Page>
```

Hand-rolled page columns (`grid-cols-*` and `col-span-*` on a page's own blocks) belong in a PageGrid.

The rendered elements carry `data-slot="page"` (with `data-flush` when flush), `data-slot="page-head"`, `data-slot="stack"`, `data-slot="page-section"`, `data-slot="page-grid"` (with `data-preset`) and `data-slot="page-grid-item"` (with `data-span`).

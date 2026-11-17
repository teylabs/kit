# LineItems

`<LineItems>` (`Kit/Table/LineItems.vue`) is the table of a frozen document: an invoice's lines, the same lines on a public share page, a member statement with a running balance. It is presentational: no sorting, selection, search or pagination (an index is a [DataTable](data-table.md)), and for a table written by hand in prose, use TextTable (docs/text.md).

```vue
<LineItems
    caption="Invoice 1042 lines"
    :columns="[
        { key: 'item', label: 'Item' },
        { key: 'description', label: 'Description' },
        { key: 'quantity', label: 'Qty', kind: 'number' },
        { key: 'price', label: 'Unit price', kind: 'money' },
        { key: 'amount', label: 'Amount', kind: 'money' },
    ]"
    :rows="invoice.lines"
    :totals="[
        { label: 'Subtotal', value: invoice.subtotal },
        { label: 'Tax (13%)', value: invoice.tax },
        { label: 'Total', value: invoice.total, emphasis: true },
    ]"
>
    <template #cell-amount="{ row, value }"><ComputedValue :value="value" /></template>
    <template #footer>Due on receipt.</template>
</LineItems>
```

| Prop | |
| --- | --- |
| `columns` | `{ key, label, align?, kind? }[]`. `kind` is `text` (the default), `money`, `number` or `date`. money and number are end-aligned; money, number and date use tabular figures and do not wrap. `align` (`start`, `end`, `center`) overrides. |
| `rows` | `Record<string, unknown>[]`. A value renders as given: format money and dates on the server, or fill the column's slot. |
| `caption` | The table's `<caption>`, which also names its scroll region. Required. |
| `captionHidden` | Keeps the caption for assistive technology only (`sr-only`), when a heading above already says it. |
| `rowHeader` | A column key whose cells are `<th scope="row">` (a statement's date, an invoice's item). |
| `totals` | `{ label, value, emphasis? }[]`, drawn in a `<tfoot>`: each value under the last end-aligned column (the amount or the balance), its label a `<th scope="row">` spanning the columns before it. `emphasis` sets the row in bold with a rule above the value. |
| `empty` | The text of the one row shown when `rows` is empty. Default "Nothing here." |

Slots: `#cell-<key>="{ row, value }"` for any column, and `#footer`, a full-width row at the end of the `<tfoot>`.

## Semantics and the phone

It is a real `<table>`: `<caption>`, `<th scope="col">` headers, row headers with `rowHeader`, totals in `<tfoot>`. On a page narrower than the table it scrolls sideways inside its own box, a focusable region named by the caption so a keyboard can scroll it, and the page itself never scrolls sideways. The first column is sticky, so a line stays identifiable while the amounts scroll into view; its cells paint `bg-background`, so on a card or another ground set that ground with a wrapper class (`[&_td:first-child]:bg-card`). Text columns keep a minimum width rather than wrapping a word per line. Colours are the theme's tokens, so light and dark follow the app.

Browser checks: `browser/run.mjs` (`?line-items`).

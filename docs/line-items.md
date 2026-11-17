# LineItems

`<LineItems>` (`Kit/Table/LineItems.vue`) is the table of a frozen document: an invoice's lines, the same lines on a public share page, a member statement with a running balance. It is presentational: no selection, search or pagination, and only an optional client-side sort for a report (an index is a [DataTable](data-table.md)), and for a table written by hand in prose, use TextTable (docs/text.md).

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
| `columns` | `{ key, label, align?, kind?, nowrap?, currency?, currencyKey?, sortable? }[]`. `kind` is `text` (the default), `money`, `number`, `percent` or `date`. money, number and percent are end-aligned; they and date use tabular figures and do not wrap. `align` (`start`, `end`, `center`) overrides. `currency` and `currencyKey` format a money column (below); `sortable` sorts by it (below). |
| `rows` | `Record<string, unknown>[]`. A value renders as given (format dates on the server, or fill the column's slot), except a numeric money or percent value, which is formatted (below). A `null` or missing value shows an em dash, read as "none"; an empty string stays empty. |
| `caption` | The table's `<caption>`, which also names its scroll region. Required. |
| `captionHidden` | Keeps the caption for assistive technology only (`sr-only`), when a heading above already says it. |
| `rowHeader` | A column key whose cells are `<th scope="row">` (a statement's date, an invoice's item). |
| `totals` | `{ label, value, emphasis? }[]`, drawn in a `<tfoot>`: each value under the last end-aligned column (the amount or the balance), its label a `<th scope="row">` spanning the columns before it. `emphasis` sets the row in bold with a rule above the value. |
| `empty` | The text of the one row shown when `rows` is empty. Default "Nothing here." |

Slots: `#cell-<key>="{ row, value }"` for any column, `#empty` in place of the `empty` text, and `#footer`, a full-width row at the end of the `<tfoot>`.

## Semantics and the phone

It is a real `<table>`: `<caption>`, `<th scope="col">` headers, row headers with `rowHeader`, totals in `<tfoot>`. On a page narrower than the table it scrolls sideways inside its own box, a focusable region named by the caption so a keyboard can scroll it, and the page itself never scrolls sideways. The first column is sticky, so a line stays identifiable while the amounts scroll into view; its cells paint their surface's colour (the `--avatar-ring` each kit surface sets: a card, a popover, a dialog; the page background elsewhere), so no wrapper class is needed. Only that first text column keeps a minimum width (rather than wrapping a word per line); other text columns, such as a short tax label, shrink to their content; give a short one `nowrap: true` to keep it on one line. Colours are the theme's tokens, so light and dark follow the app.

Browser checks: `browser/run.mjs` (`?line-items`).

## Formatting totals

`totals` values show as given. To format them like the cells (e.g. money through `Amount`, in the viewer's locale), fill `#total-value="{ total, index }"`:

```vue
<LineItems :columns="columns" :rows="rows" :totals="totals">
    <template #cell-amount="{ value }"><Amount :value="String(value)" kind="money" currency="CAD" /></template>
    <template #total-value="{ total }"><Amount :value="String(total.value)" kind="money" currency="CAD" /></template>
</LineItems>
```

## Report tables

A report page's rows are computed (sums, counts, rates), not models. Declare them in the controller with `ReportTable` and pass the result as one prop:

```php
use Tey\Kit\Features\Core\Tables\ReportTable;

return Inertia::render('Reports/Rooms', [
    'report' => ReportTable::make()
        ->column('room', 'Room')
        ->column('nights', 'Nights', kind: 'number', sortable: true)
        ->column('revenue', 'Revenue', kind: 'money', currencyKey: 'currency', sortable: true)
        ->column('occupancy', 'Occupancy', kind: 'percent', sortable: true)
        ->rowHeader('room')
        ->caption('Revenue by room')
        ->empty('No stays in this period.')
        ->rows($rows)
        ->totals([['label' => 'Total', 'value' => '$2,225.00', 'emphasis' => true]]),
]);
```

```vue
<LineItems v-bind="report" />
```

It serialises to `{ columns, rows, totals, caption?, empty?, rowHeader? }`. Each row keeps only the declared columns and their `currencyKey` fields, so nothing else on a computed row reaches the page. A duplicate key, an unknown `kind` or `align`, a currency on a non-money column and a `rowHeader` that is not a column are refused with an `InvalidArgumentException`.

- **Money**: a numeric value (`1840.5`, `"1840.50"`, in major units) is formatted in the row's currency (the field `currencyKey` names), else the column's `currency`, else the kit's default (`kitNumber`), in the kit's locale, as Amount shows it. A string that is not a number (`"$240.00"`) renders as given, so an invoice formatted on the server is unchanged.
- **Percent**: a numeric value is percentage points (`81.25` shows `81.25%`), as InputNumber's percent is.
- **Empty cells**: `null` or missing shows `—`, hidden from assistive technology, with a visually hidden "none".
- **Sorting**: a `sortable` column's header is a button. The first press sorts ascending, the next descending; the `<th>` carries `aria-sort`. number, money and percent sort numerically, other kinds as text (numeric-aware); empty cells (`null`, missing, `""`) go last in either direction, and ties keep the server's order. It sorts the rows in the browser only, and the totals stay where they are. For a large or paginated set, use a [DataTable](data-table.md) and TableQuery.

Browser checks: `browser/run.mjs` (`?line-items`, the "LineItems as a report" tests).

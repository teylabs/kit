# Row autosave

An editable table whose rows save as you edit them: rate tiers, line items, comparables. Each row saves on its own, carries its version, and shows its own state. Totals the server recomputes come back with the save, so the page never recounts them.

Lay the rows out with the kit `Repeater` (`columns` for the table layout; it already adds, removes, reorders and drags rows). Then add autosave:

```ts
import { RowSaveStatus, useRowAutosave } from '@kit/ui';

const rows = useRowAutosave({
    url: id => `/rate-tiers/${id}`,          // PATCH by default
    version: id => tiers.find(t => t.id === id)?.version ?? null,
    saved: (id, row) => Object.assign(tiers.find(t => t.id === id)!, row), // the server's copy, totals included
});
// a cell:  <InputText :model-value="row.rate" @update:model-value="v => rows.change(row.id, { rate: v })" @blur="rows.flush(row.id)" />
// last cell: <RowSaveStatus :state="rows.status[row.id]?.state ?? 'idle'" :reason="rows.status[row.id]?.reason" @reload="reloadRow(row.id)" @retry="rows.retry(row.id)" />
```

- **When it saves:** `change()` queues the edit and saves after 600 ms (`debounce`); `flush()` saves now (on blur).
- **One save per row at a time:** edits made while a row is saving wait, then go out together with the version the reply returned. Rows never wait on each other.
- **The request:** `{ ...fields, version }` through Inertia's HTTP client (the XSRF token and the host's interceptors apply). The server answers 2xx with `{ version, row? }`.
- **A stale row:** a 409, or a refusal code `stale_write`, `stale_*` or `*conflict*`, marks the row `stale`. Its edits stay unsent, so it never overwrites the newer copy. The status offers "Reload row": fetch the row and call `rows.reload(id, version)` with the server's version, then re-apply what you want to keep.
- **A failure:** the row is `failed`, its edits are kept under any later ones, and "Retry" sends them.
- **Leaving the page:** while any row has unsaved edits (queued, in flight, stale or failed), the page's pending work holds the leave guard.
- **Server-owned totals:** return the recomputed row (and any totals) as `row`, and render only the server's values. For a total with its working, send an explanation node and render it with `<ComputedValue>`.

The server checks the version in the row's own scope and answers 409 when it has moved. Keep that check on the server: the client's version is only what it last saw.

Tests: `tests/js/row-autosave.test.mjs` runs the save logic against a fake server; the Vue wrapper and the status are checked by source. No browser test edits a row.

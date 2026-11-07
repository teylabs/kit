# DataTable

`<DataTable>` (`Kit/Table/DataTable.vue`) renders a `TableData` the server builds: the rows (already filtered, sorted and paginated), the columns, the URL state and the empty state. A page that owns its query builds it with `TableQuery`; the client decides nothing about permissions.

## The server: TableQuery

```php
TableQuery::for(Member::query(), $request)
    ->column('name', 'Name', 'name')
    ->column('joinedAt', 'Joined', 'created_at')
    ->column('status', 'Status')
    ->search(fn ($query, $value) => $query->where('name', 'like', "%{$value}%"))
    ->defaultSort('joinedAt', descending: true)
    ->build(fn (Member $member) => ['id' => $member->id, 'name' => $member->name, 'joinedAt' => $member->created_at, 'status' => $member->status, 'actions' => [...]]);
```

- **Typed:** TableQuery is generic over its model (`@template TModel`). With `Builder<Member>`, `build(fn (Member $m) => …)` passes PHPStan, and a closure for another model is refused.
- **Sorting:** `defaultSort('joinedAt')` takes a column *key* and sorts by the SQL column the key was declared with (`created_at`). A requested `?sort=` wins.
- **Past the last page:** a request beyond the last page (a bookmark, or rows deleted since) redirects to the last page. It keeps the rest of the query and the session's flashed toast.
- **The generated TypeScript:** a row is `{ id; actions? } & Record<string, unknown>`, and `emptyState` is `'first-use' | 'no-results' | null`.

## Row labels: `row-label`

Each row's controls are named after it. Pass a column key, or a function of the row:

```vue
<DataTable :table="members" row-label="name" />
```

- The actions trigger reads "Actions for Ana Lopez" and the select checkbox "Select Ana Lopez".
- Without `row-label`, or when the row's value is empty, it's the row's number ("Actions for row 3").

## Row actions: links and buttons

A `get` action descriptor without a `confirm` renders as a **link**, so it can be opened in a new tab, copied, and is announced as a link:

- Inline, it's a kit Button with `href`: an Inertia Link, or an `<a target="_blank" rel="noopener noreferrer">` with `newTab`.
- In the ⌄ menu, the item itself is the link (`role="menuitem"` on the `<a>`).
- The same holds in ActionBar and its menu rows.
- Anything else stays a button: a non-GET, a `confirm`, a dialog intent, or an action the page intercepts.

## Row actions: split or overflow

Each row's actions take one of two layouts:

- **`split`**: the industry-standard split button. The primary action (the row's first non-destructive one) is joined in a ButtonGroup to a chevron menu holding every other action.
- **`overflow`**: quiet inline actions plus a "…" menu (one or two plain actions inline; three or more become the first inline plus the menu).

Without a choice, a row with exactly one primary action plus menu items is `split`, and any other row `overflow`. An explicit variant always wins:

```php
TableQuery::for(Member::query(), $request)->rowActions(variant: 'overflow')->...->build(...);
```

```vue
<DataTable :table="members" row-label="name" :row-actions="{ variant: 'split' }" />
```

The page's `:row-actions` wins over the server's `TableData` `rowActions`. `<RowActions variant="split">` takes the same prop. A row with no plain action (only a destructive one) keeps its lone quiet button in either variant.

Both variants keep the same contract: the menu trigger is named "Actions for <row>", an action in flight makes the row wait (`aria-busy` on the running control), a disabled action's `reason` is its tooltip and description, and red is only the confirm button and the text of a destructive menu item.

## Row action labels are verbs

- Keep `label` a short verb: "Remove", "Revoke", "Resend". The record goes in the accessible name and the confirmation, not the visible label.
- `Action`'s `ariaLabel` is the accessible name when the verb alone is not enough (`ariaLabel: 'Remove Billing'`). RowActions, ActionBar and BulkBar render it as `aria-label`.
- Without `ariaLabel`, RowActions names each action "<Label> <row>" ("Revoke Acme Assistant"), from the same row label as the trigger.
- A `confirm` without a `title` asks "<Label> <row>?". The Dialog wraps a long record name in full; it is never truncated.
- A visible label over 24 characters or 3 words is clamped with an ellipsis, with its full text in `title`. In development each such label warns once: "row action labels are verbs; put the record in the confirm". tey/standards' `ui.row-action-label` flags them in source.

## Status cells: Badge

```vue
<Badge :tone="member.status === 'active' ? 'success' : 'neutral'">{{ member.statusLabel }}</Badge>
```

- `tone` is neutral, info, success, warning or danger, on the kit's tone tokens (the toasts' tokens), which meet AA in light and dark. `size` is sm or md.
- The text carries the meaning; never rely on the colour alone.

## Several lists on one page means tabs

One page shows one DataTable. When a page has several lists (members and invitations, open and closed), make each list a **tab** (`TableData` `tabs`, or separate routes behind PaneTabs), not several tables stacked on one page. Each tab keeps its own URL state, search, sort and pagination, and the page keeps one toolbar and one bulk bar.

## Coverage

- `composer test:tables`: TableQuery (sorting, the key-to-column default sort, the past-the-last-page redirect and its toast, filters, selection, and the generic docblocks).
- `composer test:typescript`: TableData's generated type.
- `composer test:js` (`table-2604.test.mjs`): the row label, links versus buttons, and Badge.
- `composer test:js` (`row-action-layout.test.mjs`): the split and overflow layouts, the default between them, the clamp, the accessible name and the confirmation title.
- `browser/run.mjs` (`?row-variants`, `?datatable`): both variants, the default rule, accessible names, the clamp and a confirmation carrying a long record name, in Chromium with axe.

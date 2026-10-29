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
- Not covered: no browser renders the table.

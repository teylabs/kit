# FilterPopover

`<FilterPopover>` (`Kit/Table/FilterPopover.vue`) turns a page toolbar's filters into one control: a secondary sm "Filters" button (the SlidersHorizontal icon and a chevron) that opens a popover of the page's filters. It reads the same declarations as [DataTable](data-table.md)'s toolbar, so a page passes them and nothing else:

```vue
<FilterPopover :filters="query.choices" resettable />
```

## The declaration

A filter is TableQuery's `TableFilter` (`Tey.Kit.Features.Core.Data.TableFilter`, `FilterDescriptor` in TypeScript), extended compatibly: every new field is optional, and a declaration without them is a single-value select, as before.

| Field | Meaning |
|---|---|
| `key`, `label`, `options` | As DataTable's choices: the param name, the label, and `{ value, label }` options. |
| `kind` | `select` (the default), `multi` or `search`. |
| `default` | The value that counts as unfiltered. A value equal to it is dropped from the URL. |
| `options[].group` | A multi filter's group heading (rooms by floor). |
| `narrows` | A search filter's target: the key of the multi filter whose options it narrows. |

On the server, `TableQuery::filter()` takes `multiple: true` (comma-joined options, each validated: a part outside the options is the usual 400), `groups:` (value => heading) and `default:`. DataTable's IndexToolbar shows only the `select` choices, so a table's toolbar is unchanged by a multi filter declared beside it.

```php
->filter('rooms', fn ($q, string $value) => $q->whereIn('room', explode(',', $value)), 'Rooms', $rooms, multiple: true, groups: $floors)
->filter('status', fn ($q, string $value) => $q->where('status', $value), 'Status', $statuses, default: 'open')
```

A search filter is a client-side narrowing only: declare it on the page (`{ key: 'room-search', label: 'Search rooms', kind: 'search', narrows: 'rooms', options: [] }`); it is never sent.

## Behaviour

- **Badge**: an info Badge counts the filters whose value differs from their default (search filters never count), hidden at 0. The button's accessible name carries it ("Filters, 2 active").
- **Kinds**: a select with one option or none is hidden. A multi filter is a list of checkboxes under sticky group headings, with "All <label>" first, meaning the empty selection.
- **Apply**: a select applies at once and the popover stays open. Ticks edit a draft that applies in one visit when the popover closes, however it closes; a draft equal to the URL sends nothing. Never a visit per tick. **Escape applies the draft too**: it is the keyboard's way to close, and closing is the one moment the draft applies; nothing the person ticked is thrown away.
- **Reset** (`resettable`): every filter back to its default, in one visit, the draft cleared.
- **State**: the URL. Values are read from the query string (again after each Inertia visit), and written with `router.get(location.pathname, { ...query, ...filters }, { preserveScroll: true, preserveState: true, replace: true })`. Every unrelated param (`view`, `date`) is kept; `page` is dropped, so new filters start on the first page. Multi values are comma-joined in option order (`rooms=lobby,atrium`), as `TableQuery` reads them.
- **Scope**: by default the params are flat (`rooms=a,b`). `scope="filter"` writes them under TableQuery's envelope (`filter[rooms]=a,b`), for a page whose DataTable reads them.
- **Layout**: the popover is the only scroller and caps at the viewport height. The selects, searches and Reset are pinned at its top; each group heading sticks under the pin (its height is measured into `--filter-pin`). It stays a popover on a phone (no sheet), 22rem wide or the viewport less 2rem.

Props: `filters`, `label` ("Filters"), `resettable`, `resetLabel` ("Reset"), `scope`. It sets the kit Popover's `stayOnVisit`, so its own select visits do not close it.

Browser checks: `?filter-popover` in `browser/run.mjs` (the count, the hidden select, search, one visit on close, select, Reset, the pin and headings while scrolling, the keyboard, axe light and dark, 390px).

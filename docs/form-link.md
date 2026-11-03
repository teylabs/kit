# FormLink: downloads and new-tab submits

Some responses have to be a real browser navigation, not an Inertia visit or a `fetch`:
- a file download (`Content-Disposition: attachment`)
- a PDF opened in a new tab
- an export that takes a POST

The kit sends these through a native hidden form. It builds the form on `<body>`, submits it from script, and removes it.

Because the form belongs to the document, not a component, it still goes out when the component that asked for it has been unmounted. This matters most for a button in an `@inertiaui/modal` modal that closes as it fires.

## `<FormLink>`

```vue
<FormLink :href="route('exports.download', run)">Download CSV</FormLink>
<FormLink :href="route('reports.pdf', report)" target="_blank">Open PDF</FormLink>
<FormLink :href="route('exports.store')" method="post" :data="{ ids: selected }" @submitted="modal.close()">Export selected</FormLink>
```

- **`href`** is where the form goes.
- **`method`** is `get` (the default), `post`, `put`, `patch` or `delete`. `put`, `patch` and `delete` are sent as a POST with `_method`, which Laravel reads.
- **`data`** becomes hidden fields named the way Laravel reads them back: `ids[0]`, `filters[open]`.
  - Booleans are sent as `1` and `0`; `null` is sent as empty.
  - A GET keeps the URL's own query string; a browser would otherwise drop it from a GET form's action.
  - Files can't be sent this way, and passing one throws. Upload the file with the form, then link to the result.
- **`target`**: `_self` (the default) suits a download, because an attachment saves the file and the page stays where it is. Use `_blank` for a new tab.
- **`as`** is the element or component drawn; a `button` by default. The slot is its label.
- **`disabled`** stops the submit.
- **`@submitted`** fires after the form is sent; close the modal there. `submit()` is exposed so the form can be sent from script.

**Popup blockers.** FormLink submits during the click, so a `_blank` tab is not blocked.

**CSRF.** A non-GET submit adds `_token` from `<meta name="csrf-token">` when the page has one. Without one, Laravel accepts a same-origin form by its `Sec-Fetch-Site` header (`PreventRequestForgery`).

## `submitHiddenForm(options)`

This is the same hidden form, for code that isn't a click handler on a control: `submitHiddenForm({ url, method, data, target, token })`. `hiddenFields(data)` is the flattening it uses.

## `useNewTabForm(payload)`

Validate on the server first, then open the result:

```ts
const { form, open } = useNewTabForm({ period: '2026-10' });
open(route('exports.validate'), (page) => route('exports.show', page.props.export), { onOpened: () => modal.close() });
```

1. `form` (an Inertia `useForm`) posts to the validate URL, and shows its errors as usual.
2. Only once that succeeds does a hidden GET open the target URL, in a new tab by default. The target can be a function of the page the validation returned.

The hidden form is built on `<body>` because a validating POST that redirects back can tear down a modal, and everything in it, before `onSuccess` runs.

Options:
- `target` (`_blank`, or `_self` for a download)
- `data`, the fields for the GET, or a function of the page
- `onOpened`

Browsers allow a new tab only for a few seconds after the click. Keep validation quick. For a slow job, queue it and download the result where it lands.

**Tests.** `browser/run.mjs` covers:
- a GET, a DELETE and validate-then-open, each from a stand-in modal that unmounts as it fires
- the browser sends each request, with the URL's query string kept and nested data flattened
- a DELETE goes as a POST with `_method`
- the hidden form is removed afterwards
- the page stays put

**Not covered:**
- A real `@inertiaui/modal` teardown; a stand-in modal unmounts instead.
- A popup blocker in a headed browser. Downloads use `_self`, so they don't open popups.

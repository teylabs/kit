# useSubmitForm

`useSubmitForm(initial, handler, options?)` is a form whose save is the app's own async handler (a `fetch`, Inertia's `useHttp` through `httpSubmit`, a socket) instead of an Inertia visit. It never touches the router: no visit, no new history entry, no page swap. `FormCard`, `FormActions` and `Workbench` take its result as `:form`, just as they take a `useForm()`. It's part of core, exported from the kit barrel.

```vue
<script setup lang="ts">
import { Field, FormCard, Input, SubmitError, useSubmitForm } from '@kit/ui';

const form = useSubmitForm({ title: '', body: '' }, async (data) => {
    if (!data.title.trim()) throw new SubmitError({ title: 'Give the post a title.' });
    const response = await fetch('/api/posts', { method: 'POST', headers: { 'content-type': 'application/json', accept: 'application/json' }, body: JSON.stringify(data) });
    if (!response.ok) throw response;
}, { timeout: 15000 });
</script>

<template>
    <FormCard :form="form" title="New post">
        <Field id="title" label="Title" :error="form.errors.title"><Input id="title" v-model="form.data.title" name="title" :invalid="!!form.errors.title" /></Field>
    </FormCard>
</template>
```

Given a `useSubmitForm()` result, with no Inertia `<Form>` around it, `FormCard` renders the `<form novalidate>` itself. Enter and Save call `form.submit()`, a failure moves focus to the first invalid field, and the form's `_form` message shows above the bar. `submit` stays the server's action descriptor (its label, and `disabled` with a `reason`). A `useForm()` is never treated this way: the card tells the two apart by a brand on the result (`isSubmitForm()`), never by whether the form has a `submit()` method.

## The result

What `FormActions` reads from a `useForm()`: `processing`, `isDirty`, `hasErrors`, `recentlySuccessful`, `progress` (always null), `reset(...fields)`, `clearErrors(...fields)` and `resetAndClearErrors()`. On top of that:

- `data`: the fields, for `v-model`.
- `errors`: one plain-text message per field, plus `_form` for a message that names no field.
- `submit()`: resolves `true` on success and `false` on a failure. It never rejects. A submit while one is processing is ignored and resolves `false` at once.
- `setDefaults(values?)`: with no argument the current data becomes the defaults; with values, only those fields' defaults change.
- `conflict`: the last submit was refused as a stale write.
- `warnOnLeave`: whether the leave-page guard applies to this form.

`isDirty` is a deep compare of `data` against the defaults. A success moves the defaults to the submitted data, so the form is clean again, and `recentlySuccessful` is true for about 2 seconds.

## The handler

The handler gets a copy of the data (through `transform` when given). It must return a Promise: an `async` function, or one that returns the request. The kit calls it as `Promise.resolve().then(() => handler(data))`, so a synchronous throw is a failure like any other, never an exception out of `submit()`.

## With Inertia's `useHttp`

Don't await `useHttp` on its own in a handler. It doesn't reject on a 422: it calls `onError` and resolves `undefined`, so the handler returns normally and a refused save reads as a success. Wrap the request in `httpSubmit` and pass it the hooks it gives you:

```ts
import { useHttp } from '@inertiajs/vue3';
import { httpSubmit, useSubmitForm } from '@kit/ui';

const http = useHttp({});
const form = useSubmitForm({ title: '', body: '' }, httpSubmit((data, hooks) => http.transform(() => data).post('/api/posts', hooks)));
// With options of your own: http.put(url, { ...hooks, headers: { 'X-Version': version } })
```

What each `useHttp` outcome becomes (as of `@inertiajs/vue3` 3.9.1):

| useHttp does | httpSubmit |
|---|---|
| 2xx: resolves the parsed body (`null` for an empty one) | resolves with it: a success |
| 422: calls `onError(errors)`, resolves `undefined` | rejects with a `SubmitError` of the field errors (the first message per field) |
| 409 and any other status: calls `onHttpException`, throws an `HttpResponseError` with `response` | rejects with that error: a 409 `stale_write` sets `conflict`, any other status is the generic message |
| network failure: calls `onNetworkError`, throws | rejects with that error: the generic message |
| cancelled: throws `HttpCancelledError` | rejects: the generic message |
| resolves `undefined` with no `onError` call (the hooks weren't passed) | rejects with the generic message |

It never resolves on a failure. Pass `onError` through the hooks, not on your own: one of your own in the same options would replace it.

## Errors

A failure is read in this order:

1. **`SubmitError`**: `throw new SubmitError({ title: 'Required.' }, 'Fix the title.')` maps the fields, and the optional summary becomes `_form`. It's for a refusal the client decides itself, with no request made.
2. **A thrown fetch `Response`, or an error with a `response`** (the `HttpResponseError` `useHttp` throws for a status other than 2xx and 422): the body is read through the kit's `http.ts` (`responseJson` and `errorJson`), so a string body or an object both work.
   - A **422** whose `errors` is Laravel's shape maps to the fields.
   - A **409 `{ "error": "stale_write" }`** sets `conflict`, with a `_form` line. The edits are kept.
   - **Any other status**, or a 422 or 409 whose body doesn't match, is the generic `_form` message.
3. **A thrown plain object of Laravel's 422 shape** (`{ title: ['Required.'] }`) maps to the fields.
4. **Anything else** is the generic `_form` message: "This could not be saved. Try again."

Laravel's shape means exactly a non-empty plain object whose every value is a non-empty list of strings. Anything else, an `errors` that is a string, a list, or a field whose value isn't a list of strings, is never mapped: it's the generic message. Only the first message per field is kept.

**Server text is never HTML.** The kit renders every message as text (`{{ }}`, never `v-html`), and keeps at most `SUBMIT_MESSAGE_MAX` (300) characters; a longer one is cut and ends with an ellipsis. A field named `__proto__`, `constructor` or `prototype` is dropped.

The 409 body is the one kit's stale-write guard answers (`GuardsStaleWrites::updateIfCurrent()`, a `RuntimeException('stale_write')`), for a JSON request and an Inertia request alike. `tests/fixtures/stale-write-409.json` holds it; the PHP check sends a stale save through the real kernel and compares the response to that file, and the JS test feeds the same file to `useSubmitForm`, so the two halves can't drift.

## Options

| Option | Does |
|---|---|
| `timeout` | Milliseconds. A handler that hasn't settled by then fails with `_form` "Timed out. Try again." The handler isn't cancelled: pass an `AbortSignal` of your own if the request should stop too. Off by default. |
| `resetOnSuccess` | `true`: after a success, every field goes back to the default it had before the submit (a "new comment" box that empties). A list: only those fields; the others' defaults move to the submitted values. |
| `transform` | `(data) => payload`: what the handler receives, built from a copy of the data. |
| `warnOnLeave` | `false`: no leave-page guard for this form, in `FormActions` or the Workbench. The default is unchanged: an unsaved form asks before leaving ([Leaving the page](#leaving-the-page)). |

## Leaving the page

`FormActions`, `FormCard` and a Workbench with `:form` guard the page while it's unsaved. Unsaved means the form is dirty or shows errors, or **pending work** is registered (below). Leaving then asks first in the frame's "Leave this page?" dialog: Stay keeps the page and the edits, Leave goes on. The guard covers:

- **An Inertia visit** to another page (a GET). A partial reload, a visit to the same path and any other method go through.
- **Back and Forward.** Where the browser has the Navigation API, a same-document traverse is cancelled and, on Leave, made again. Elsewhere (Safari, Firefox without it) the guard adds one history entry for the page while it's unsaved: Back lands on the entry below with the page kept, and asks; Leave goes back once more. The entry is taken off again when the page is clean, when a save starts, and before a confirmed visit, so it never leaves a duplicate entry behind. The fallback puts back the scroll position Inertia resets.
- **Closing or reloading the tab**, through `beforeunload` (the browser's own prompt).

**The save's own follow-up goes through.** From the form's submit (or `processing` turning on) until `processing` is off and every visit started meanwhile has finished, the guard is saving and holds no Inertia visit: Inertia's redirect, a `router.visit()` in `onSuccess` (where a `useForm()` is still dirty), or one after `await form.submit()`. While a save is in flight, Back still asks where the Navigation API exists; the history fallback takes its entry off for the save, so there Back isn't held.

`warnOnLeave: false` (here, or `warn-on-leave="false"` on the frame) registers none of it, for a host that owns its own guard.

### Pending work

`usePendingWork()` is the page's registry of work the form doesn't see yet: an upload in flight, a draft being written, an autosave. While any is registered, the guard treats the page as unsaved, even with a clean form.

```ts
import { usePendingWork } from '@/components/Kit';

const work = usePendingWork();
await work.track(uploadPoster(file)); // registered until it settles; resolves or rejects as it does

const end = work.begin(); // or by hand
try { await saveDraft(); } finally { end(); } // end() is idempotent
```

`work.pending` (a computed boolean) and `work.count` are readable for a status of your own. BulkDrop's `direct` uploads register themselves. `FileInput` starts no upload of its own: its File is sent with the form's submit, which the guard already treats as the save. `createPendingWork()` makes a separate registry, which the guard doesn't read.

## Tests

- **`node --test tests/js/submit-form.test.mjs`** runs `useSubmitForm` over the real `http.ts`. It covers every error path above, `httpSubmit` over a stand-in for `useHttp` (422, 409 `stale_write`, 500, a network failure, a success, and a bare `useHttp` handler that reads a 422 as success), the timeout, `resetOnSuccess` (`true` and a list), a double submit, dirty after the defaults move, the HTML and length cap, a synchronous throw and `transform`.
- **`composer test:workspace-models`** sends a stale guarded save through the real kernel and handler and checks the response against `tests/fixtures/stale-write-409.json`.
- **`bin/typecheck-components.sh`** (`typecheck/fixtures/SubmitForm.vue`): a typed `useSubmitForm<T>()` keeps its field keys, the handler must return a Promise, and `FormCard`, `FormActions` and `Workbench` take the result, and `httpSubmit`'s hooks are valid `useHttp` options.
- **`node --test tests/js/pending-work.test.mjs`** covers the pending-work registry: `begin()` and an idempotent `end()`, `track()` on a resolve and a reject, and one page registry.
- **The browser job, leave guard** (`leave.html`, on the real `@inertiajs/vue3`): Back with a dirty form asks, Stay keeps the page, Leave goes back; once clean again Back doesn't ask; both with the Navigation API and with `window.navigation` deleted (the history fallback, which leaves no entry behind). A BulkDrop upload in flight counts as unsaved on a clean form, and a save whose `onSuccess` visits another page isn't held.
- **The browser job** (`?form-submit`): a `FormCard` over a fake socket handler. Enter submits with no navigation, Save is disabled while pending, a 422 shows the field error and summary, then Saved; axe passes, colour contrast included.

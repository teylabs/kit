# useSubmitForm

`useSubmitForm(initial, handler, options?)` is a form whose save is the app's own async handler (a `fetch`, Inertia's `useHttp`, a socket) instead of an Inertia visit. It never touches the router: no visit, no new history entry, no page swap. `FormCard`, `FormActions` and `Workbench` take its result as `:form`, just as they take a `useForm()`. It's part of core, exported from the kit barrel.

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

## Errors

A failure is read in this order:

1. **`SubmitError`**: `throw new SubmitError({ title: 'Required.' }, 'Fix the title.')` maps the fields, and the optional summary becomes `_form`. It's for a refusal the client decides itself, with no request made.
2. **A thrown fetch `Response`, or an error with a `response`** (Inertia's `useHttp` and `HttpResponseError`): the body is read through the kit's `http.ts` (`responseJson` and `errorJson`), so a string body or an object both work.
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
| `warnOnLeave` | `false`: no leave-page guard for this form, in `FormActions` or the Workbench. The default is unchanged: a dirty form asks before leaving. |

## Tests

- **`node --test tests/js/submit-form.test.mjs`** runs `useSubmitForm` over the real `http.ts`. It covers every error path above, the timeout, `resetOnSuccess` (`true` and a list), a double submit, dirty after the defaults move, the HTML and length cap, a synchronous throw and `transform`.
- **`composer test:workspace-models`** sends a stale guarded save through the real kernel and handler and checks the response against `tests/fixtures/stale-write-409.json`.
- **`bin/typecheck-components.sh`** (`typecheck/fixtures/SubmitForm.vue`): a typed `useSubmitForm<T>()` keeps its field keys, the handler must return a Promise, and `FormCard`, `FormActions` and `Workbench` take the result.
- **The browser job** (`?form-submit`): a `FormCard` over a fake socket handler. Enter submits with no navigation, Save is disabled while pending, a 422 shows the field error and summary, then Saved; axe passes, colour contrast included.

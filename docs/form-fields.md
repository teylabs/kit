# Field errors and the FormActions bar

## A Field's error, by name

`<Field name="…">` shows the error for `name` from the enclosing Inertia `<Form>` (`errors[name]`); `error` passes one explicitly and wins. Laravel keys a nested field's error with dots (`items.0.name`), while a native control posts a bracket path (`items[0][name]`). A Field finds the error whichever way either is written:

| `name` | Finds |
| --- | --- |
| `title` | `errors.title` |
| `items[0][name]` | `errors['items.0.name']` or `errors['items[0][name]']` |
| `items[0].name` | the same |
| `items.0.name` | the same |

An exact key wins. Nothing else about a Field changes: the same markup, label, description and error, and the control is still described by the error and `aria-invalid` while there is one.

### In a Repeater row

Inside a Repeater row, a Field named under that row (with the row slot's `name('amount')`, which is `lines[1][amount]` today) reads the row's own errors: the form's, re-keyed from the row's place when the server answered to its current place. Move or remove a row and each error stays with the row it belongs to, not with the position.

```vue
<Repeater name="lines" :rows="lines" :template="{ amount: '' }" item-label="Line" add-label="Add line">
    <template #row="{ row, name }">
        <Field :id="`amount-${row.id}`" :name="name('amount')" label="Amount">
            <Input :id="`amount-${row.id}`" :name="name('amount')" :model-value="row.amount" />
        </Field>
    </template>
</Repeater>
```

You no longer pass `errors[name('amount')]` to the Field yourself. A Field in a row named outside the row (`title`) reads the form's errors, as anywhere else. `fieldError(errors, name)` is the lookup, in `Form/repeaterState.ts`.

## FormActions density

`FormActions` is the bar at a form's foot: a top border, 24px side padding and 12px above and below, with a 12px gap. That stays the default. `density="compact"` is the bar inside something small that already has its own edge and padding, such as a comment composer or a card's footer row: no border or padding of its own and an 8px gap. Status, Cancel, the submit button, the leave guard and the submit gate are the same.

```vue
<FormActions density="compact" :sticky="false" :cancel="false" submit-label="Post" />
```

A compact bar is usually not sticky: pass `:sticky="false"` when it sits in a small box. It carries `data-density="compact"`.

## Choosing a select: InputSelect or Select

`InputSelect` is the default for a choice in a form. Reach for the plain `Select` only when every answer below points to it; this is a judgement call, and no check fails on either.

Ask at build time:

1. **Does the list come from data, or can it grow?** (records, users, tags, anything a person or a seeder adds to) → `InputSelect`. It turns search on past 8 options and takes `filter="remote"`.
2. **Will anyone need to search it?** → `InputSelect`.
3. **Is the field optional, or must the person be able to clear it?** → `InputSelect` with `clearable`. A plain `Select` has no clear control: once chosen, the only way back is an option for "none".
4. **Does "no choice" need its own label** ("Any scope", "No team")? → `InputSelect`, with the label as its `placeholder` and `clearable`. A plain `Select` can carry an option whose value is `''`, and shows its label (fixed in v0.39.3), but nothing tells the reader it means "none".

A plain `Select` is right for a short, fixed list the code owns (a handful of enum cases that never change at runtime), that always holds a value, needs no search and has no "none" choice: a status on a dense settings row, a unit beside a number, a role picked from three. A choice that changes what a page shows (a sort order, a view) is neither: use `InlineDropdown`.

## InputSelect: select-only or searchable

A short fixed list is never typeable. `searchable` left unset is off for up to 8 local options (`SEARCH_THRESHOLD` in `inputSelect.ts`) and on above that and for `filter="remote"`; pass it to override. A select that is not searchable is a select-only combobox that reads as a button: a pointer cursor, no text caret or selection, and no on-screen keyboard (`inputmode="none"`, `data-select-only`). Only a searchable select shows a text cursor. For a choice that changes what a page shows (a sort order, a view), use `InlineDropdown` (docs/inline-dropdown.md) rather than a bordered field. The browser job (`?inline-dropdown`) reads both kinds: the select-only field's cursor, caret and `inputmode`, typing into it changing nothing, and the searchable one's text cursor.

## Coverage

- **Node:** `tests/js/number.test.mjs` covers `fieldError`: bracket, mixed and dot names against dot-keyed and bracket-keyed errors, another field of the same row, an index that is a prefix of another (`2` and `20`) and an exact key winning.
- **Browser (`?field-paths`):** Fields named `title`, `contacts[0][email]`, `contacts[0].email` and `contacts.1.name` show Laravel's dot-path errors, are `aria-invalid` and described by the error; a Field with no error is described by its description only; the default Field's classes are unchanged. A Repeater row's error shows on its row's Field and follows the row when it moves up. The default FormActions bar keeps its border, padding and gap; the compact bar has none of its own and an 8px gap. axe with contrast, light and dark.
- **Not covered:** a host's real Inertia `<Form>` (the fixture provides the context Inertia does) and a screen-reader run.

## Secret fields (API keys, tokens)

Use `<PasswordInput secret />` for a value that is pasted in rather than remembered: an API key, a token or a webhook signing secret. It sets `autocomplete="new-password"`, `data-1p-ignore`, `data-lpignore="true"` and `data-bwignore`, so browsers and password managers (1Password, LastPass, Bitwarden) don't fill a saved login into it. That autofill is what made such a form show "Unsaved changes" on load. The toggle reads "Show value". An attribute you pass yourself wins.

A kit form is not marked unsaved when autofill leaves the loaded value, because `isDirty` compares against the defaults. It clears its unsaved state after a successful save. If your `onSuccess` calls `form.defaults(...)` itself, Inertia leaves the defaults to you.

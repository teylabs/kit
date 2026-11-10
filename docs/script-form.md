# ScriptForm: forms the page posts by script

Most forms save through Inertia's `<Form>` with `FormCard` or `FormActions`. A few are a ceremony the page runs itself: Add a passkey confirms the password with Fortify, then asks the browser for a passkey (WebAuthn) and posts the result. No visit can do that, so the form is never posted by the browser.

`<ScriptForm>` is that form:

```vue
<ScriptForm class="flex flex-wrap items-end gap-2" :busy="adding" @submit="addPasskey">
    <Field id="password" name="password" label="Your password" :error="error ?? undefined"><PasswordInput id="password" v-model="password" required /></Field>
    <Button type="submit" :disabled="adding">Add a passkey</Button>
</ScriptForm>
```

- It renders a `<form>` marked `data-script-form`, so Enter and the submit button work, `required` is checked by the browser, and a form guard can tell it from a hand-rolled frame.
- **`@submit`** is emitted with the event (the default is always prevented). It is not emitted while **`busy`** is true, so a double press doesn't start a second ceremony; `busy` also sets `aria-busy`. The slot gets `busy`.
- Errors stay the page's own: the handler knows which field failed or that the ceremony was cancelled, so it sets the Field's `error` or shows an `Alert`.
- Classes and other attributes go on the `<form>`.

Use it only when the page's own script makes the requests (a ceremony, an allowed non-Inertia target). A form that saves fields goes through `<Form>`, or `useSubmitForm` with `FormCard` (docs/submit-form.md).

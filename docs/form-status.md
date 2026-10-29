# FormStatus

`<FormStatus>` is a form's persistent confirmation after a submit: "We have emailed your password reset link", "You're on the list". It isn't a toast: it never times out, and it stays until the person submits again or leaves. It's part of core, exported from the kit barrel, and composes `Alert` rather than restyling it.

```vue
<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Button, Field, FormStatus, Input } from '@kit/ui';
</script>

<template>
    <Form action="/waitlist" method="post" class="grid gap-4">
        <FormStatus />
        <Field id="email" name="email" label="Email address"><Input id="email" name="email" type="email" /></Field>
        <Button type="submit">Join the waitlist</Button>
    </Form>
</template>
```

- **The text.** By default it's the `status` page prop, Fortify's and Laravel's flashed `status` (session-ui passes it through each page's view model). `message` overrides it; `:message="null"` shows nothing, for a page that maps a status code to its own words (VerifyEmail maps `verification-link-sent`).
- **Announced politely.** It renders `<Alert live="status">`: `role="status"`, announced once when it appears, never assertive.
- **`tone`:** `success` (default), `info` or `warning`, Alert's variants of the same names.
- **How long it stays.** The next Inertia visit's `start` hides it, and so does `form.processing` when you pass `form` (a `useForm()` result). When that visit finishes it shows again only if the new response still carries a status. No timers. Its router listeners are removed when it unmounts.
- **Nothing when empty.** With no message it renders nothing.

session-ui uses it on ForgotPassword (the reset link sent), VerifyEmail (a new link sent) and Login (the password was reset). Other attributes (`class`, `data-*`) pass to the Alert.

`composer test:js` (`tests/js/form-status.test.mjs`) covers the source: the Alert and its live region, the tone mapping, the precedence of `message`, hiding on `start` and `form.processing`, the listeners' removal, the export and the pages.

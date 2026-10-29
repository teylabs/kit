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

- **The text.** By default it's the `status` page prop, Fortify's and Laravel's flashed `status` (session-ui passes it through each page's view model). `flash-key` reads Inertia v3's page flash instead: `<FormStatus flash-key="notice" />` shows `usePage().flash.notice`, what `Inertia::flash('notice', …)` sets (v3 keeps flash data at `page.flash`, not `page.props.flash`). `message` overrides both; `:message="null"` shows nothing, for a page that maps a status code to its own words (VerifyEmail maps `verification-link-sent`). The order is `message`, then `flash-key`, then `status`. A blank or non-string value shows nothing.
- **Announced politely.** It renders `<Alert live="status">`: `role="status"`, announced once when it appears, never assertive.
- **`tone`:** `success` (default), `info` or `warning`, Alert's variants of the same names.
- **How long it stays.** It shows whenever there is a message and no visit is in flight. In flight means the router is between a visit's `start` and `finish`, or `form.processing` is true when you pass `form` (a `useForm()` result). That holds wherever it is mounted, before or after the visit. When a visit finishes it shows again only if the new page carries a message. No timers. Its router listeners are removed when it unmounts.
- **Nothing when empty.** With no message it renders nothing.

session-ui uses it on ForgotPassword (the reset link sent), VerifyEmail (a new link sent) and Login (the password was reset). Other attributes (`class`, `data-*`) pass to the Alert.

`composer test:js` (`tests/js/form-status.test.mjs`) covers the source: the Alert and its live region, the tone mapping, the precedence of `message`, `flashKey` and `status`, visibility computed from the message and the in-flight state, the listeners' removal, the export and the pages. The browser job (`browser/run.mjs`, `?form-status`) mounts it before the visit and drives visits through an Inertia stub in v3's order (`start`, the page swap, `flash`, `success`, `finish`): shown after a submit whose response carries `status`, hidden while a second submit is in flight and shown with the new message after, a `flash-key` message (a pre-mounted `flash-key="resetLink"` showing a forgot-password flash from `page.flash`), nothing after a response with no message, and `form.processing` without a router visit.

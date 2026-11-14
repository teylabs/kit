# ActionGroup

`<ActionGroup>` (`Kit/Action/ActionGroup.vue`, in `@kit/ui`) holds a form's own actions, Buttons and links styled as buttons, as one group with one gap: 12px (`gap-3`). It is layout only: it never restyles its children, runs nothing and adds no role, so each control keeps its own name and tab order. For server action descriptors use `ActionBar`; for a settings form's foot use `FormActions`.

## Direction

`direction` is `row` (the default) or `column`.

- **`row`:** one row that wraps, the actions vertically centred, in the order written.
- **`column`:** stacked, every action the group's full width, in the order written. It is for an auth-style card (sign in, two-factor, verify email, an invitation), so the primary action goes first. It is the same at every breakpoint: no `sm:` or `md:` switch back to a row.

```vue
<Form action="/two-factor-challenge" method="post" class="grid gap-4">
    <Field id="code" name="code" label="Authentication code"><Input id="code" name="code" /></Field>
    <ActionGroup direction="column">
        <Button type="submit">Verify</Button>
        <Button variant="secondary" type="button" @click="recovery = !recovery">Use a recovery code</Button>
    </ActionGroup>
</Form>
```

A child's own width, `self-*` or `justify-self-*` gives way to the group's in a column: the group sets each direct child to its full width. `as` and `as-child` are Reka's Primitive's, as on `Stack` and `Toolbar`.

## The kit's own pages

Login (Sign in, then Sign in with a passkey where the browser can), TwoFactor (Verify, then the code toggle), VerifyEmail (Send the link again, then Sign out) and the invitation page (accept or join, then Switch account and Cancel; Switch account, then Cancel, for the wrong account) stack their actions with `<ActionGroup direction="column">`. The invitation page's single "Sign in to join →" style links are one next step each, not a stack, and stay as they are.

## Coverage

`composer test:js` (`tests/js/action-group.test.mjs`) checks the props, the barrel, the contract and that each of those pages puts its primary action first in a column group. The browser job measures a fixture (`?action-group`) and the pages at 1280 and 390px: in a column every action is the group's full width, in the order written, 12px apart, and a row keeps its actions on one line, 12px apart.

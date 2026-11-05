# Single actions

When a page places its actions by name instead of handing a list to `ActionBar`, the server sends a keyed map of action descriptors (`label`, `url`, `method`, `icon`, `confirmationMessage`, `description`, `variant`, `target`). A missing or null entry means the action isn't allowed. The template places each one:

```vue
<ButtonGroup>
    <InertiaAction :action="actions.view" />
    <template #menu>
        <DropdownAction :action="actions.edit" />
        <DropdownAction :action="actions.remove" />
    </template>
</ButtonGroup>
```

- **`InertiaAction`** is a button. With a `confirmationMessage` it asks first in the kit's Dialog, otherwise it's an Inertia link with the action's method. A `target` opens a plain link there.
- **`DropdownAction`** is the menu-item twin, confirming through `DropdownConfirm`.
- **Both** take a default slot that replaces the label, and render nothing for a null action.
- **`ActionBar`** is still there for a list of actions that should fit and overflow on its own.

Tests: `browser/` renders neither yet; they're typechecked with the other components.

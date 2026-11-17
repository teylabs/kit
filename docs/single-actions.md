# Single actions

When a page places its actions by name instead of handing a list to `ActionBar`, the server sends a keyed map of the kit's `Action` (`confirm`, `destructive`, `disabled` with `reason`, `newTab`, `icon`; the older keyed-link shape with `confirmationMessage`, `variant` and `target` also works). A missing or null entry means the action isn't allowed. The template places each one:

```vue
<ButtonGroup>
    <InertiaAction :action="actions.view" />
    <template #menu>
        <DropdownAction :action="actions.edit" />
        <DropdownAction :action="actions.remove" />
    </template>
</ButtonGroup>
```

- **`InertiaAction`** is a button. With a confirmation it asks first in the kit's Dialog (typed when the `Action` has `typed`), otherwise it visits with the action's method; a GET is an Inertia link, and `newTab` opens a plain link with `rel="noopener noreferrer"`. `disabled` shows its `reason`.
- **`DropdownAction`** is the menu-item twin, confirming through `DropdownConfirm`. A typed confirmation can't be asked from a menu, so it shows that action disabled: use an `InertiaAction` for it.
- **Both** take a default slot that replaces the label, and render nothing for a null action.
- **`ActionBar`** is still there for a list of actions that should fit and overflow on its own.

Tests: `browser/` renders neither yet; they're typechecked with the other components.

## Downloads

A GET that answers with a file (a CSV export, a PDF) is a download, not navigation: declare it with `->download()` (or `download: true`).

```php
'export' => (new Action('Export CSV', route('reports.rooms.csv'), 'get'))->download(),
```

Every place that renders actions (InertiaAction, DropdownAction, a Page or PageSection header's ActionBar and its More menu, RowActions inline, split and in its menu, CollectionSection) draws it as a plain `<a href download>` with the Download icon (RowActions' inline text buttons show no icon): no `target`, no new-tab arrow, and never an Inertia visit, so the browser saves the file and the page stays. `download` wins over `newTab`. A confirmed or disabled download stays a button, as any other action. Browser checks: `browser/run.mjs` (`?download`).

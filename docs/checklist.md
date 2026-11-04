# Checklist

`Checklist` draws a list of steps that the server has worked out, such as build steps (Preparing → Rendering → Saving), a setup or readiness list, or a job's stepper. The server decides each step's state, and the component only draws it, so the page never guesses.

## Server

```php
use Tey\Kit\Features\Core\Data\ChecklistAction;
use Tey\Kit\Features\Core\Data\ChecklistItem;

$steps = [
    new ChecklistItem('preparing', 'Preparing the report', 'done'),
    new ChecklistItem('rendering', 'Rendering the PDF', 'failed', reason: 'The renderer timed out.', action: new ChecklistAction('Try again', name: 'retry')),
    new ChecklistItem('saving', 'Saving the preview'),
];
```

- **`state`:** `pending`, `active`, `done`, `failed` or `skipped`. Any other value is refused when the item is built.
- **`detail`:** a line under the label.
- **`reason`:** why a step failed or was skipped.
- **`required: false`:** an optional step. It never holds back completion.
- **`action`:** a link (`url`) or a named action (`name`). The component emits a named action as `action` and the page handles it.

## Client

```vue
<Checklist :items="steps" label="Building the preview" @action="item => item.action?.name === 'retry' && retry()">
    <template #actions="{ complete }">
        <Button :disabled="!complete" @click="open">Open the preview</Button>
    </template>
</Checklist>
```

- The steps render as an ordered list. The active step has a spinner and `aria-current="step"`.
- A done step is ticked. A failed step shows its reason as an alert. A skipped step is struck through, with its reason.
- A polite status line announces the current step, a failure, or "All steps done".
- **`item` slot:** replaces a row's body.
- **`actions` slot:** adds a last row. It gets `complete` (every required step is done or skipped, and nothing failed) and `progress`.
- **`labels`:** renames the announcement and the screen-reader suffixes.
- **Headless use:** `checklistProgress(items)` returns `{ total, settled, done, failed, complete, current, percent }`. `useChecklist(items)` is its reactive form, for a host that draws its own list.
- **Fails closed:** an unknown state from a newer server counts as pending, and an empty list is never complete.

**Mapping from a run's steps:** a job runner's step (`{ key, label, status, error }`) maps directly: `state` is `status` and `reason` is `error`.

Tests: `tests/js/checklist.test.mjs` covers progress and announcements, and checks the component's markup by source. `tests/LibraryCheck.php` covers the server data. No browser test renders the list.

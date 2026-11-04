# Pending actions and running work in a modal

A kit action shows that its request is in flight, and never fires twice. The host writes no spinner code.

- **Dialog actions** (`intent: 'dialog'`) open through InertiaUI's `ModalLink`, sending the action's `method`. While the modal visit loads, the button's icon becomes a spinner and the button is `aria-busy`. `ModalLink` ignores clicks while loading, so a slow visit never stacks two dialogs. A non-GET dialog doesn't push a URL.
- **Visits the runner makes** (ActionBar, HeaderActions): the running action spins, and every action in the bar waits until the visit finishes (`onStart`/`onFinish`). An action the host runs itself (`@action`) is the host's to guard.
- **A modal opened from code:** use `useModalVisit()`.

```ts
import { useModalVisit } from '@kit/ui';

const preview = useModalVisit();
// <Button :disabled="preview.loading.value" @click="preview.open(url, { method: 'post' })">
```

`open()` resolves to the modal, or to `null` for an open ignored because one is in flight.

## Running work in a modal

For an action that starts background work and shows its progress (a report preview, an export), use one request:

1. The action is a dialog descriptor with `method: 'post'`.
2. Its controller starts the Docket run, or reuses the one already running for the same subject, and answers with the modal, passing the run's ticket as a prop.
3. The modal renders the run from that ticket: the Docket dialog body streams it and passes its steps to the kit `<Checklist>`.

The client sends no separate POST. A second click is ignored while the modal loads, and a repeat after it opens reuses the running work. Docket's ticket and start-or-reuse helpers are documented in Docket.

Not covered here: the tests check the components' source and types; no browser test opens a slow modal.

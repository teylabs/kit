# EmptyState

`<EmptyState>` says there's nothing here yet and offers the next step. The core feature owns it in `Kit/Feedback/EmptyState.vue`.

```vue
<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import { Button, EmptyState } from '@/components/Kit';
</script>
<template>
    <EmptyState :icon="Inbox" title="No invoices yet" body="Invoices you send appear here.">
        <template #action><Button @click="create">New invoice</Button></template>
    </EmptyState>

    <!-- In a panel or a list, with the server's action descriptors -->
    <EmptyState compact title="No notes" :actions="page.actions" @action="run" />
</template>
```

- `title` is the heading, at `level` (h3 by default). `body` is a line under it; the default slot replaces it.
- `icon` is a component, and the `icon` slot replaces it. It's decorative (`aria-hidden`).
- The next step is the `action` slot (a kit Button) or `actions`, server action descriptors rendered by an ActionBar. Its `action` event hands back the descriptor you passed.
- `compact` is the smaller form for a panel, a list or a table body. The default is centred for a whole page section.
- It's a plain region labelled by its title, **never a live region**. It's there when the view renders, so announcing it would be noise. For something that appears after an action, use a toast.

Tailwind utilities and the kit's tokens only.

## Coverage

`composer test:js` (`description-empty.test.mjs`) checks the markup, that it has no live role, the action slot and descriptors, and the exports, by source. Not covered: no browser renders it.

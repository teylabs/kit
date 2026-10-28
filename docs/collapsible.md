# Collapsible

`<Collapsible>` is a section that opens and closes (a disclosure), for an inspector's sections such as Position or Effects. The core feature owns it in `Kit/Layout/Collapsible.vue`.

```vue
<script setup lang="ts">
import { Collapsible } from '@/components/Kit';
const effectsOpen = ref(false);
</script>
<template>
    <Collapsible title="Position" default-open>
        <PositionFields />
    </Collapsible>
    <Collapsible v-model:open="effectsOpen" title="Effects">
        <template #actions><Button variant="ghost" size="sm" @click="reset">Reset</Button></template>
        <EffectsFields />
    </Collapsible>
</template>
```

| Prop | What it does |
|---|---|
| `title` | The heading text inside the toggle |
| `v-model:open` | Whether it's open. Without it, the section keeps its own state. |
| `default-open` | The starting state when `open` isn't bound; closed by default |
| `level` | The heading level, 2 to 6; 3 by default |
| `id` | The section's id. It also seeds the toggle's and content's ids. |

**Slots.**
- `summary` replaces the `title` text inside the toggle. Keep it to text and icons, because it sits inside a button.
- `actions` sits beside the toggle, outside the button.
- The default slot is the content.

All three get `{ open }`.

**Accessibility.**
- The toggle is a real `<button>` inside the heading, so Enter and Space work natively.
- The button has `aria-expanded` and `aria-controls` naming the content region, and the region is labelled by the button.
- While the section is closed, the content is hidden (`display: none`), so it's out of the tab order and the accessibility tree, not just invisible.
- The height and opacity transition, and the chevron's turn, are off under `prefers-reduced-motion`.
- It uses utilities and tokens only.

`CollapsibleProps` is in the contract.

**Tests.** `composer test:js` (`tests/js/workbench-editor-gaps.test.mjs`) checks the source: the button, `aria-expanded`, the `aria-controls`/`id` link, `v-model:open` with `defaultOpen`, and the `motion-reduce:` utilities. **Not covered:** there's no browser test. The kit has no DOM test runner, so opening, keyboard use and the transition aren't exercised in a real browser.

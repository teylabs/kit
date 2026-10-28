# RadioCardGroup

`<RadioCardGroup>` is a set of large, tappable choices, such as a poll's options on a phone. The core feature owns it in `Kit/Form/RadioCardGroup.vue`.

```vue
<script setup lang="ts">
import { Field, RadioCardGroup } from '@/components/Kit';
const answer = ref<string | number | null>(null);
</script>
<template>
    <Field id="answer" label="Your answer" name="answer">
        <RadioCardGroup id="answer" v-model="answer" name="answer" :options="poll.options" :show-results="poll.closed" />
    </Field>
</template>
```

- Each option is a card around a native radio. The browser gives the group one tab stop, and the arrow keys move and select within it (the radio group's roving tabindex). The value submits with a plain or Inertia `<Form>` under `name`, which defaults to `id`.
- It's a `role="radiogroup"`, named by its Field's label (Field now gives its label the id `<id>-label`) or by `label`. It's described by the Field's description and error.
- `v-model` is the selected value. The selected card takes the primary ring. A `disabled` option, or a `disabled` group, is dimmed and can't be chosen.
- Options are `{ value, label, description?, disabled?, result? }`. With `show-results`, each option's `result` (0 to 100) shows as a bar and a percentage. The `result` slot (`{ option, selected, percent }`) replaces it.
- The cards are at least 56px tall, with a visible focus ring. The result bar's animation is off under reduced motion.

## Coverage

`composer test:js` (`radio-emoji.test.mjs`) checks the markup, the selected and disabled states, the result bar and the exports, by source. The components vue-tsc job compiles a fixture using it in a Field with `v-model`. Not covered: no browser renders it or takes a tap.

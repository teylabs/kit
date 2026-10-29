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

## Commit on click: `commit="click"`

For a live poll, where each choice is sent at once, a vote shouldn't change while someone is still arrowing through the options. `commit="click"` does that:

```vue
<RadioCardGroup id="vote" v-model="vote" label="Your vote" commit="click" :options="poll.options" @commit="send" />
```

- The arrow keys, Home and End only move focus: one tab stop, wrapping and skipping disabled options. Nothing is chosen while arrowing.
- Space, Enter, a click or a tap commits. It sets `v-model` and emits `commit` with the value, once.
- Pressing the option that's already chosen emits nothing.
- The committed choice is announced politely ("B chosen"). A hidden input still submits it under `name`.
- **This deliberately deviates from the APG radio group**, where arrowing selects. The group keeps `role="radiogroup"` and each card is a `role="radio"` button with `aria-checked`, but selection follows a press, not focus. Use it only where an accidental arrow would send something. The default, `change`, is the native group, where arrowing selects.

## Coverage

`composer test:js` (`radio-emoji.test.mjs`, and `radio-commit.test.mjs` for `commit="click"`) checks the markup, the selected and disabled states, the result bar and the exports, by source. The components vue-tsc job compiles a fixture using it in a Field with `v-model`. Not covered: no browser renders it or takes a tap.

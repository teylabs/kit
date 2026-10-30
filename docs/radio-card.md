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
- It's a `role="radiogroup"`, named by its Field's label (Field now gives its label the id `<id>-label`) or by `label`. It's described by the Field's description and error, and by any `aria-describedby` you pass, which joins them.
- Other attributes you pass (`data-*`, `aria-*`) land on the radiogroup. `invalid` sets its `aria-invalid`. `required` sets its `aria-required` and, in the default `change` mode, the native radios' `required`.
- `v-model` is the selected value. The selected card takes the primary ring. A `disabled` option, or a `disabled` group, is dimmed and can't be chosen.
- Options are `{ value, label, description?, disabled?, result? }`. With `show-results`, each option's `result` (0 to 100) shows as a bar and a percentage. The `result` slot (`{ option, selected, percent }`) replaces it.
- A `disabled` option is a native disabled radio: it can't be chosen, and the arrow keys skip it, as the browser does for any radio group.
- The cards are at least 56px tall, with a visible focus ring. The result bar's animation is off under reduced motion.

## Custom cards, image tiles and grids

```vue
<RadioCardGroup id="avatar" v-model="avatar" name="avatar" columns="auto" :options="avatars.map(a => ({ value: a.id, image: { src: a.url, alt: a.name } }))" />

<RadioCardGroup id="plan" v-model="plan" name="plan" :columns="3" :options="plans">
    <template #option="{ option, checked, disabled }">
        <span class="text-lg font-semibold">{{ option.label }}</span>
        <span v-if="checked" class="text-sm">Selected</span>
    </template>
</RadioCardGroup>
```

- The `option` slot (`{ option, checked, disabled }`) replaces a card's body. The native radio, the `<label>` around it, the focus ring and the checked ring stay the kit's, so the group still has one tab stop, the arrow keys and form submission. The slot's content is the radio's accessible name; keep it short and put details in `description`.
- An option with `image: { src, alt }` and no slot is an image tile: the image fills a square box (`aspect-square`, `object-cover`) with `loading="lazy"` and `decoding="async"`, so a grid of about 100 tiles loads as it scrolls without layout shift. A `label`, if given, shows under the image and names the radio (the image is then decorative, `alt=""`); without one, the image's `alt` names it.
- Every option needs a `label` or an image `alt`. The option type requires one, and in development the component warns about an option with neither.
- A checked image or slot tile shows the primary ring and a check mark in its corner, so the state isn't colour alone. The mark is `aria-hidden`: the radio carries the state.
- `columns` lays the cards out in a grid: `2`, `3`, `4` or `6` (fewer on a phone), or `auto`, which fills each row with tiles at least 7rem wide. Without it the cards stack in one column.

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

`composer test:js` (`radio-emoji.test.mjs`, and `radio-commit.test.mjs` for `commit="click"`) checks the markup, the selected and disabled states, the result bar, the option slot, the image tile and the columns, and the exports, by source. The components vue-tsc job compiles a fixture using it in a Field with `v-model`. The browser job (`browser/run.mjs`, `?form-attrs`) renders it and checks that `aria-describedby`, `aria-invalid` and `required` reach the radiogroup and its radios, with axe. `?tiles` renders an `option` slot group and an image tile grid, and checks the custom content, that a disabled tile can't be chosen and ArrowRight skips it, an image tile's name from its `alt`, the check mark on the checked tile, and axe with colour contrast. Not covered: no browser takes a tap.

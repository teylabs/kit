# Slider

`<Slider>` picks a number on a range, such as a volume or a percentage. The core feature owns it in `Kit/Form/Slider.vue`.

```vue
<script setup lang="ts">
import { Field, Slider } from '@/components/Kit';
const volume = ref(40);
</script>
<template>
    <Field id="volume" label="Volume" name="volume" description="How loud alerts play.">
        <Slider id="volume" v-model="volume" name="volume" :step="5" :value-text="v => `${v}%`" show-value @change="save" />
    </Field>
</template>
```

- It's a native `<input type="range">`. The browser gives it the slider role and its value, the arrow keys, Page Up and Page Down, Home and End, and drag. It submits with a plain or Inertia `<Form>` under `name`.
- `v-model` is the number; it's `min` when unset. `min`, `max` and `step` default to 0, 100 and 1.
- It emits `input` with the value live while it's dragged or stepped, and `change` once on release. Save on `change`.
- `valueText` turns the value into `aria-valuetext` ("40%"), which a screen reader reads instead of the bare number.
- `show-value` shows the value beside the slider in an `<output>` linked to the input by `for`, formatted by `valueText`. It isn't a live region, so it doesn't announce every step.
- In a Field, give it the Field's `id`. The input is then labelled by the Field and described by its description and error, after any `aria-describedby` you pass.
- Other attributes you pass (`name`, `data-*`, `aria-*`, `class`) land on the input, not a wrapper. `invalid` sets `aria-invalid`. `disabled` is the native one.
- It's styled with tokens: the primary accent (destructive when `invalid`) and the ring colour for keyboard focus.
- Inside a locked `LockedForm` it's read-only: still focusable and announced, with `aria-readonly`, and a key or a drag puts the value back. A `disabled` you pass still wins.

## Coverage

`composer test:js` (`slider.test.mjs`) checks the markup, the events, the attribute forwarding, the locked state and the exports, by source. The components vue-tsc job compiles a fixture using it in a Field. The browser job (`browser/run.mjs`, `?slider`) checks the label, `aria-valuetext`, live `input` events on keyboard steps and a drag with one `change` on release, the Field's `aria-describedby` on the input, and axe with colour contrast. Not covered: no browser takes a touch drag, and no screen reader is run.

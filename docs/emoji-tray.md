# EmojiTray

`<EmojiTray>` is a compact reaction picker, for example an audience reacting from a phone. It shows a short, configurable row of emoji rather than a full picker. The core feature owns it in `Kit/Form/EmojiTray.vue`.

```vue
<script setup lang="ts">
import { EmojiTray, type EmojiReaction } from '@/components/Kit';
const react = (reaction: EmojiReaction) => router.post(route('talks.reactions.store', talk), { emoji: reaction.emoji }, { preserveScroll: true });
</script>
<template>
    <EmojiTray :throttle="1500" @react="react" />
    <EmojiTray :reactions="[{ emoji: '🔥', label: 'Fire' }, { emoji: '🤔', label: 'Thinking' }]" label="React to this slide" @react="react" />
</template>
```

- `reactions` is a list of `{ emoji, label }`. The default is `DEFAULT_REACTIONS`, six common ones.
- It's a `role="toolbar"` named by `label` ("Reactions" by default). It has a roving tabindex: one tab stop, and the arrow keys, Home and End move between reactions.
- Each button is named by its reaction's label. The emoji itself is hidden from screen readers.
- A press emits `react` at most once every `throttle` milliseconds (1000 by default) across the whole tray. Presses inside the window are dropped, not queued, so a mashed button can't flood the server.
- The press animation is off under reduced motion. Each button is 44px.

## Coverage

`composer test:js` (`radio-emoji.test.mjs`) runs the throttle and the roving keys, and checks the markup, the labels and the reduced motion by source. The components vue-tsc job compiles a fixture using its `react` event. Not covered: no browser renders it.

# StageNotice

`<StageNotice>` is an announcement laid over a stage: a slide, a canvas or a projected screen. Examples: "Live demo: Billing", "Recording", "Back in 5 minutes". It's part of core and exported from the kit barrel.

```vue
<script setup lang="ts">
import { StageNotice } from '@kit/ui';
</script>

<template>
    <div class="relative @container aspect-video">
        <SlideCanvas />
        <StageNotice tone="info" placement="top" :show="live">Live demo: {{ label }}</StageNotice>
    </div>
</template>
```

- **Inside its stage.** It's positioned absolutely, so the stage needs `relative`. It's sized in container-query units (`cqw`), so the stage needs a container (`@container`) and the notice scales with the stage, not the viewport.
- **Never in the way.** `pointer-events: none`: the stage underneath stays usable, and the notice can't be selected.
- **`tone`:** `info` (default), `success`, `warning` or `danger`, the same colours as Alert. **`tier`:** `solid` (default, read across a room) or `pale`.
- **`placement`:** `top` (default), `bottom`, `top-left`, `top-right`, `bottom-left` or `bottom-right`.
- **`show`:** toggles it with a short fade and a slide from its own side. With reduced motion it appears and goes without moving.
- **`live`:** `polite` (default) announces a change as a status; `off` is for a notice meant only for the room.
- **Sizes:** custom properties a host sets on the stage: `--stage-notice-size` (the text, `2.5cqw`) and `--stage-notice-inset` (the distance from the edges, `3cqw`). It uses Tailwind utilities and these properties only.

`composer test:js` (`tests/js/stage-notice.test.mjs`) covers the placements, tones, the reduced-motion transition, the component's source and its export.

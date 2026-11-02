# SegmentedToggle

`<SegmentedToggle>` is a neutral view switch: one choice of a few, side by side, such as an editor's "Slides | Outline" or a grid or list layout. The core feature owns it in `Kit/Form/SegmentedToggle.vue`.

```vue
<script setup lang="ts">
import { LayoutGrid, List } from '@lucide/vue';
import { ref } from 'vue';
import { SegmentedToggle } from '@/components/Kit';

const view = ref('slides');
const layout = ref('grid');
</script>
<template>
    <SegmentedToggle v-model="view" label="Editor view" :options="[{ value: 'slides', label: 'Slides' }, { value: 'outline', label: 'Outline' }]" />
    <SegmentedToggle v-model="layout" label="Layout" size="sm" :options="[{ value: 'grid', label: 'Grid', icon: LayoutGrid }, { value: 'list', label: 'List', icon: List }]" />
</template>
```

- Props: `v-model` (the selected value), `options` (`{ value, label, icon?, disabled? }`), `label` (the group's accessible name, its `aria-label`; required) and `size` (`default` or `sm`).
- **Neutral selection.** The selected segment is a raised surface, `bg-background` with a `border-muted-foreground` border and a small shadow, inside a `bg-muted` track. It's never brand-filled, so a view switch doesn't read as a second primary action beside the page's real one.
- **A radio group.** It's a `role="radiogroup"` of `role="radio"` buttons with `aria-checked`. A view switch is a single choice that is always set, which is what a radio group says; toggle buttons (`aria-pressed`) suit independent on/off switches. The group is one tab stop, on the selected segment. The arrow keys move and select, wrapping at the ends, and Home and End go to the first and last. A `disabled` option is dimmed, can't be clicked and is skipped by the keys.
- The keyboard-focused segment shows a 2px `ring`-coloured outline.
- **Contrast** (kit.css's default tokens, WCAG 1.4.11 non-text 3:1 for the selected border; labels 4.5:1):

  | | Border on track (`--muted`) | Border on segment (`--background`) | Label on track | Label on segment |
  | --- | --- | --- | --- | --- |
  | Light | 4.35:1 | 4.74:1 | 18.16:1 | 19.80:1 |
  | Dark | 5.77:1 | 7.85:1 | 13.94:1 | 18.97:1 |

  A host theme that changes `--muted-foreground`, `--muted` or `--background` should keep the border at 3:1 against both.
- Each segment carries `data-value` (the option's value) and the selected one `data-selected`: hooks for browser tests and styling.

The Workbench's `PaneTabs` (its phone tabs) take the same neutral look. They stay a single-choice toggle group (`role="group"` with `aria-pressed` buttons), since they switch the pane in place.

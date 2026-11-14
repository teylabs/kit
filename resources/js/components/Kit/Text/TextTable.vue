<script setup lang="ts">
import { TABLE, TEXT_SIZES, type TextSize } from './text';

/*
 * A plain <table> the app writes itself, styled for reading (docs/text.md): full width, a rule under the header and between rows, padded cells. It scrolls
 * sideways inside its own box when wider than the page, as a region named by `label` that takes focus, so a keyboard can scroll it too. `size` is sm (the
 * default), base or lg. For sorting, selection or pagination, use DataTable.
 */
withDefaults(defineProps<{ label?: string; size?: TextSize }>(), {
    label: undefined,
    size: 'sm',
});
</script>
<template>
    <div
        :role="label ? 'region' : undefined"
        :aria-label="label"
        tabindex="0"
        :class="[
            'focus-visible:outline-ring w-0 min-w-full overflow-x-auto rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2',
            TEXT_SIZES[size],
            TABLE,
        ]"
        data-slot="text-table"
    >
        <slot />
    </div>
</template>

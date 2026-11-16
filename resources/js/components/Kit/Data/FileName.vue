<script setup lang="ts">
import MiddleTruncate from './MiddleTruncate.vue';

/*
 * A file name cut in the middle to fit its box: the start, then the extension and the last few characters before it ("Screenshot-2026-11…36.11-AM.png"),
 * never an end ellipsis that hides `.png` (docs/middle-truncate.md, "FileName"). MiddleTruncate with `kind="file"`; `min-tail` (default 6) is how many
 * characters before the extension always survive. The full name is the `title` and the accessible text. A block: give it a bounded width (`min-w-0`).
 */
withDefaults(defineProps<{ name: string; minTail?: number }>(), {
    minTail: 6,
});
</script>
<template>
    <MiddleTruncate
        :value="name"
        kind="file"
        :min-tail="minTail"
        data-file-name
    />
</template>

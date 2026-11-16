<script setup lang="ts">
import { computed } from 'vue';
import { TONE_DOT, toneOf } from './tones';

/*
 * A small round dot in one of the kit's named tones (tones.ts): beside a label, a legend entry or a filter chip. Decorative unless it has a `label`: then it is
 * an image named by the label (for a dot that stands alone). The colour never carries meaning alone: put the words next to it, or give it a label.
 */
const props = defineProps<{ tone?: string | null; label?: string }>();
const tone = computed(() => toneOf(props.tone));
</script>
<template>
    <span
        :class="['inline-block size-2 shrink-0 rounded-full', TONE_DOT[tone]]"
        :role="label ? 'img' : undefined"
        :aria-label="label || undefined"
        :aria-hidden="label ? undefined : 'true'"
        :data-tone="tone"
        data-colour-dot
    />
</template>

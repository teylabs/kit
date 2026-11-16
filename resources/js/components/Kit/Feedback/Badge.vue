<script setup lang="ts">
import { computed } from 'vue';
import ColourDot from './ColourDot.vue';
import { TONE_BADGE, toneOf, type Tone } from './tones';

/*
 * A short status label (a table's status cell, a count): `tone` is one of the kit's named tones (tones.ts): neutral, info, success, warning and danger, and
 * purple, teal, pink, orange and slate for categories, each on the kit's tone tokens (a pale background and a strong foreground, AA in light and dark).
 * `dot` puts the tone's dot before the text. `size` sm or md. The text is the status: a colour never carries meaning alone. Not a live region and not
 * interactive; for a removable chip, use a button. Utilities and tokens only.
 */
const props = withDefaults(
    defineProps<{ tone?: Tone; size?: 'sm' | 'md'; dot?: boolean }>(),
    { tone: 'neutral', size: 'sm', dot: false },
);
const tone = computed(() => toneOf(props.tone));
const sizes = { sm: 'h-5 px-1.5 text-xs', md: 'h-6 px-2 text-sm' } as const;
</script>
<template>
    <span
        :class="[
            'inline-flex max-w-full shrink-0 items-center gap-1 rounded-md border font-medium whitespace-nowrap',
            TONE_BADGE[tone],
            sizes[size],
        ]"
        :data-tone="tone"
        data-badge
        ><ColourDot v-if="dot" :tone="tone" /><span class="truncate"
            ><slot /></span
    ></span>
</template>

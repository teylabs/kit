<script setup lang="ts">
import ColourDot from './ColourDot.vue';

/*
 * Muted text with a tone's dot before it ("● In progress"): a quieter status than a Badge, for a list's meta line or a legend. `dot` false drops the dot.
 * The words carry the meaning; the dot is decorative.
 */
withDefaults(
    defineProps<{ label: string; tone?: string | null; dot?: boolean }>(),
    { tone: 'neutral', dot: true },
);
</script>
<template>
    <span
        class="text-muted-foreground inline-flex items-center gap-1.5 text-sm"
        data-tone-label
    >
        <ColourDot v-if="dot" :tone="tone" />
        {{ label }}
    </span>
</template>

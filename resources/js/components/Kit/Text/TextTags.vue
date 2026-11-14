<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/components/Kit/Feedback/Badge.vue';
import { badgeItems, type BadgeTone } from './text';

/*
 * Read-only tags (docs/text.md): a list of short labels, each a neutral kit Badge, wrapping onto more lines as needed. A real list, so a screen reader
 * counts them; `label` names it. Blank items are dropped and nothing renders without one. `tone` and `size` are the Badge's. To edit tags, use InputTags; for
 * a label with a value ("Status: Active"), TextChips.
 */
const props = withDefaults(
    defineProps<{
        items: readonly string[] | null | undefined;
        label?: string;
        tone?: BadgeTone;
        size?: 'sm' | 'md';
    }>(),
    { label: undefined, tone: 'neutral', size: 'md' },
);
const tags = computed(() => badgeItems(props.items));
</script>
<template>
    <ul
        v-if="tags.length"
        :aria-label="label"
        class="flex flex-wrap items-center gap-1.5"
        data-slot="text-tags"
    >
        <li v-for="tag in tags" :key="tag.key" class="flex min-w-0">
            <Badge :tone="tone" :size="size">{{ tag.label }}</Badge>
        </li>
    </ul>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/components/Kit/Feedback/Badge.vue';
import { badgeItems, type TextChip } from './text';

/*
 * Read-only chips (docs/text.md): a list of kit Badges, each a label, or a label with its value ("Plan: Pro", the label in the regular weight), each with its
 * own `tone` (neutral by default). A real list, so a screen reader counts them; `label` names it. Blank items are dropped and nothing renders without one.
 * The words carry the meaning, never the tone alone. For plain tags, use TextTags.
 */
const props = withDefaults(
    defineProps<{
        items: readonly TextChip[] | null | undefined;
        label?: string;
        size?: 'sm' | 'md';
    }>(),
    { label: undefined, size: 'md' },
);
const chips = computed(() => badgeItems(props.items));
</script>
<template>
    <ul
        v-if="chips.length"
        :aria-label="label"
        class="flex flex-wrap items-center gap-1.5"
        data-slot="text-chips"
    >
        <li v-for="chip in chips" :key="chip.key" class="flex min-w-0">
            <Badge :tone="chip.tone" :size="size"
                ><template v-if="chip.value !== null"
                    ><span class="font-normal">{{ chip.label }}:</span>{{ ' '
                    }}{{ chip.value }}</template
                ><template v-else>{{ chip.label }}</template></Badge
            >
        </li>
    </ul>
</template>

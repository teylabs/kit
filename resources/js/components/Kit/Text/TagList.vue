<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Badge from '@/components/Kit/Feedback/Badge.vue';
import Popover from '@/components/Kit/Overlay/Popover.vue';
import { type Tone } from '@/components/Kit/Feedback/tones';
import { shownTags, type TagItem } from './tagList';

/*
 * A record's flags and tags (docs/flags.md), from the server's `tags` (Tags::for): the flags the system raised first, the most severe first, then
 * people's own tags. They wrap freely; only past `max` (8 by default; null never collapses) do the rest fold into "+N more", which opens them in a
 * popover. A flag with a link is a link; its reason is its hover text and is read out after its label. A real list, named by `label`. The words carry
 * the meaning, never the tone alone.
 */
const props = withDefaults(
    defineProps<{
        tags: readonly TagItem[] | null | undefined;
        label?: string;
        max?: number | null;
        size?: 'sm' | 'md';
    }>(),
    { label: 'Tags', max: 8, size: 'sm' },
);
const split = computed(() => shownTags(props.tags, props.max));
const tone = (tag: TagItem): Tone => tag.tone as Tone;
</script>
<template>
    <ul
        v-if="split.shown.length"
        :aria-label="label"
        class="flex flex-wrap items-center gap-1"
        data-slot="tag-list"
    >
        <li
            v-for="tag in split.shown"
            :key="tag.key"
            class="flex min-w-0"
            :data-kind="tag.kind"
        >
            <component
                :is="tag.href ? Link : 'span'"
                :href="tag.href ?? undefined"
                :title="tag.tooltip ?? undefined"
                class="focus-visible:ring-ring inline-flex min-w-0 rounded-md focus-visible:ring-2 focus-visible:outline-none"
                :class="tag.href ? 'hover:underline' : undefined"
                ><Badge :tone="tone(tag)" :size="size"
                    >{{ tag.label
                    }}<span v-if="tag.tooltip" class="sr-only"
                        >: {{ tag.tooltip }}</span
                    ></Badge
                ></component
            >
        </li>
        <li v-if="split.hidden.length" class="flex">
            <Popover :title="label" width="sm">
                <template #trigger
                    ><button
                        type="button"
                        class="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded-md px-1 text-xs font-medium focus-visible:ring-2 focus-visible:outline-none"
                        data-slot="tag-list-more"
                    >
                        +{{ split.hidden.length }} more
                    </button></template
                >
                <ul class="flex flex-wrap gap-1" :aria-label="label">
                    <li v-for="tag in split.hidden" :key="tag.key" class="flex">
                        <component
                            :is="tag.href ? Link : 'span'"
                            :href="tag.href ?? undefined"
                            :title="tag.tooltip ?? undefined"
                            class="inline-flex rounded-md"
                            ><Badge :tone="tone(tag)" :size="size"
                                >{{ tag.label
                                }}<span v-if="tag.tooltip" class="sr-only"
                                    >: {{ tag.tooltip }}</span
                                ></Badge
                            ></component
                        >
                    </li>
                </ul>
            </Popover>
        </li>
    </ul>
</template>

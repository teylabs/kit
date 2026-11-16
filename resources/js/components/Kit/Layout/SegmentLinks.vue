<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ColourDot from '@/components/Kit/Feedback/ColourDot.vue';

export interface SegmentLink {
    key: string;
    label: string;
    href: string;
    count?: number | null;
    tone?: string | null;
}

/*
 * In-place segments (docs/segment-links.md): a row of links that filter a list by URL, each with an optional count and tone dot, the `current` one marked
 * `aria-current="page"`. Each is an Inertia Link that keeps state and scroll; `navigate` hears the click first (event, href), so a page can take over the
 * visit (a partial reload with `only`) by calling `event.preventDefault()`. Not a form control (SegmentedToggle), local panes (PaneTabs) or page tabs (PageTabs).
 * `appearance` link reads as links; neutral as quiet chips.
 */
withDefaults(
    defineProps<{
        segments: SegmentLink[];
        current?: string | null;
        label?: string;
        appearance?: 'link' | 'neutral';
    }>(),
    { current: null, label: 'Filter', appearance: 'link' },
);
const emit = defineEmits<{ navigate: [event: MouseEvent, href: string] }>();
</script>
<template>
    <nav
        :aria-label="label"
        class="flex max-w-full flex-wrap items-center gap-1"
        :data-appearance="appearance"
        data-segment-links
    >
        <Link
            v-for="segment in segments"
            :key="segment.key"
            :href="segment.href"
            preserve-scroll
            preserve-state
            :aria-current="segment.key === current ? 'page' : undefined"
            :class="[
                'focus-visible:outline-ring inline-flex items-center gap-1.5 rounded-md font-medium whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2',
                appearance === 'neutral'
                    ? 'hover:text-foreground min-h-10 px-3 py-1.5 text-sm'
                    : 'px-2 py-1.5 text-xs underline-offset-2 hover:underline',
                segment.key === current
                    ? 'bg-muted text-muted-foreground ring-border ring-1'
                    : [
                          'hover:bg-muted/60',
                          appearance === 'neutral'
                              ? 'text-muted-foreground'
                              : 'text-link',
                      ],
            ]"
            :data-segment="segment.key"
            @click.capture="emit('navigate', $event, segment.href)"
        >
            <ColourDot v-if="segment.tone" :tone="segment.tone" />
            {{ segment.label }}
            <span
                v-if="segment.count != null"
                class="text-muted-foreground tabular-nums"
                data-segment-count
                >{{ segment.count }}</span
            >
        </Link>
    </nav>
</template>

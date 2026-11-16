<script setup lang="ts">
import { ImageIcon } from '@lucide/vue';
import { computed } from 'vue';

export type MediaPicture = { thumb: string | null; card?: string | null };

/*
 * A fixed-size square picture from a record's `thumb` (400) and `card` (800) conversions, as a srcset, lazy. With no picture, or before its thumb exists,
 * it is a muted tile with an image icon: never the original.
 */
const props = withDefaults(
    defineProps<{
        picture: MediaPicture | null;
        alt: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { size: 'md' },
);
const box = computed(
    () => ({ sm: 'size-12', md: 'size-20', lg: 'size-32' })[props.size],
);
const sizes = computed(
    () => ({ sm: '3rem', md: '5rem', lg: '8rem' })[props.size],
);
const srcset = computed(() =>
    props.picture?.thumb && props.picture.card
        ? `${props.picture.thumb} 400w, ${props.picture.card} 800w`
        : undefined,
);
</script>
<template>
    <img
        v-if="picture?.thumb"
        :src="picture.thumb"
        :srcset="srcset"
        :sizes="sizes"
        :alt="alt"
        loading="lazy"
        :class="[box, 'border-border shrink-0 rounded-md border object-cover']"
        data-media-thumbnail
        :data-size="size"
    />
    <span
        v-else
        role="img"
        :aria-label="alt"
        :class="[
            box,
            'border-border bg-muted text-muted-foreground inline-flex shrink-0 items-center justify-center rounded-md border',
        ]"
        data-media-thumbnail
        data-media-pending
        :data-size="size"
    >
        <ImageIcon class="size-1/3" aria-hidden="true" />
    </span>
</template>

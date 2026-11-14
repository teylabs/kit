<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import SeeAllLink from '@/components/Kit/Action/SeeAllLink.vue';
import type { SeeAll } from '@/components/Kit/Action/seeAll';
import { groundCard, useGroundSurface } from '@/components/Kit/Surface/surface';
import { cn } from '@/components/Kit/utils';
import SectionHeader from './SectionHeader.vue';

/*
 * One titled region of a page, a direct child of <Page> (or of a PageGrid cell): a <section> named by its title, with an optional SectionHeader (`title`,
 * `description`, descriptor `actions`, heading `level`, 2 by default) above its content, which it stacks with a Stack's rhythm (`gap-4`). It carries no outer
 * margin: the Page's gap separates it from its siblings. With no `title` it is a plain stacked group; pass `aria-label` to name it. `action` is emitted as
 * SectionHeader emits it.
 * `surface`: on a tinted page ground (README, "Tinted page grounds") the content sits on a card (`card`, the default there) and the SectionHeader stays on the
 * ground above it; `none` leaves it on the ground. On a white ground, or inside a card already, no card is drawn and nothing changes.
 * `seeAll` (docs/see-all.md): a list section's "All cases →" link, at the bottom right after the content (`footer`, the default, inside the card when one is
 * drawn), or in the SectionHeader after its actions (`header`, for a summary, stat or chart; a section with no title has no header, so it stays in the footer).
 */
const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string | null;
        actions?: ActionDescriptor[];
        level?: 2 | 3 | 4 | 5 | 6;
        surface?: 'card' | 'none';
        seeAll?: SeeAll | null;
        class?: HTMLAttributes['class'];
    }>(),
    {
        title: undefined,
        description: null,
        actions: () => [],
        level: 2,
        surface: 'card',
        seeAll: null,
        class: undefined,
    },
);
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
const card = useGroundSurface(() => props.surface !== 'none');
const footerSeeAll = computed(() =>
    props.seeAll && (props.seeAll.placement !== 'header' || !props.title)
        ? props.seeAll
        : null,
);
</script>
<template>
    <section
        :aria-label="title"
        :class="cn('flex flex-col gap-4', props.class)"
        data-slot="page-section"
    >
        <SectionHeader
            v-if="title"
            :title="title"
            :description="description"
            :actions="actions"
            :level="level"
            :see-all="seeAll"
            @action="(action: ActionDescriptor) => emit('action', action)"
        />
        <div
            v-if="card"
            :class="cn('flex flex-col gap-4 p-6', groundCard)"
            data-ground-surface
        >
            <slot />
            <div
                v-if="footerSeeAll"
                class="flex justify-end"
                data-see-all-footer
            >
                <SeeAllLink :see-all="footerSeeAll" />
            </div>
        </div>
        <template v-else>
            <slot />
            <div
                v-if="footerSeeAll"
                class="flex justify-end"
                data-see-all-footer
            >
                <SeeAllLink :see-all="footerSeeAll" />
            </div>
        </template>
    </section>
</template>

<script setup lang="ts">
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import SectionHeader from '@/components/Kit/Layout/SectionHeader.vue';
import { provideCard } from '@/components/Kit/Surface/surface';

// One part of a record's detail page (docs/detail-section.md): the heading (and its actions) on the page ground, the body on a card of its own. A
// DescriptionList, List or EmptyState inside draws no second card.
withDefaults(
    defineProps<{
        title: string;
        description?: string | null;
        actions?: ActionDescriptor[];
        level?: 2 | 3;
    }>(),
    { description: null, actions: () => [], level: 2 },
);
defineEmits<{ action: [action: ActionDescriptor] }>();
provideCard();
</script>
<template>
    <section
        :aria-label="title"
        class="flex flex-col gap-3"
        data-detail-section
    >
        <SectionHeader
            :title="title"
            :description="description"
            :actions="actions"
            :level="level"
            @action="(a: ActionDescriptor) => $emit('action', a)"
        />
        <div
            class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border p-6"
            data-detail-body
        >
            <slot />
        </div>
    </section>
</template>

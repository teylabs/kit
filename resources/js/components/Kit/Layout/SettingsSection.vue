<script setup lang="ts">
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import SectionHeader from '@/components/Kit/Layout/SectionHeader.vue';
import { provideCard } from '@/components/Kit/Surface/surface';

// One block of a settings page (docs/settings-section.md): a title and description, the body in the default slot, and footer `actions` (or a `#footer` slot).
// It draws its own card on any ground, like the form Section, so a List or EmptyState inside draws none.
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
        class="bg-card text-card-foreground divide-border divide-y rounded-xl border shadow-xs"
        data-settings-section
    >
        <div class="px-6 py-4">
            <SectionHeader
                :title="title"
                :description="description"
                :level="level"
            />
        </div>
        <div
            v-if="$slots.default"
            class="flex flex-col gap-4 p-6"
            data-settings-body
        >
            <slot />
        </div>
        <div
            v-if="$slots.footer || actions.length"
            class="flex justify-end gap-2 px-6 py-3"
            data-settings-footer
        >
            <slot name="footer"
                ><ActionBar
                    :actions="actions"
                    @action="(a: ActionDescriptor) => $emit('action', a)"
            /></slot>
        </div>
    </section>
</template>

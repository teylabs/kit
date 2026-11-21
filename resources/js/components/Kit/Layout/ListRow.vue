<script setup lang="ts">
import { useInCard } from '@/components/Kit/Surface/surface';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';

// One row of a List: a title with an optional description (or the default slot), a `meta` slot beside it (a Badge, a date) and descriptor `actions` at the end.
withDefaults(
    defineProps<{
        title?: string;
        description?: string | null;
        actions?: ActionDescriptor[];
    }>(),
    { title: undefined, description: null, actions: () => [] },
);
defineEmits<{ action: [action: ActionDescriptor] }>();
// Inside a card the row is flush with the card's padding; on the ground the List's own card gives it a side inset.
const inCard = useInCard();
</script>
<template>
    <li
        :class="[
            'flex flex-wrap items-center justify-between gap-3 py-3',
            inCard ? 'px-0 first:pt-0 last:pb-0' : 'px-4',
        ]"
        data-list-row
    >
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <slot>
                <span class="text-foreground text-sm font-medium">{{
                    title
                }}</span>
                <span
                    v-if="description"
                    class="text-muted-foreground text-sm"
                    >{{ description }}</span
                >
            </slot>
        </div>
        <div v-if="$slots.meta" class="flex items-center gap-2 text-sm">
            <slot name="meta" />
        </div>
        <ActionBar
            v-if="actions.length"
            :actions="actions"
            @action="(a: ActionDescriptor) => $emit('action', a)"
        />
    </li>
</template>

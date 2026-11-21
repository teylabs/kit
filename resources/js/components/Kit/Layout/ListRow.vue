<script setup lang="ts">
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
</script>
<template>
    <li
        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
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

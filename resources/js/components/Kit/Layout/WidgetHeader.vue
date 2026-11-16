<script setup lang="ts">
/*
 * The header row of a Widget: an avatar, the heading (named by `headingId`, so the Widget's section is labelled by it) with a meta line under it, a badge,
 * then the actions at the end. `fill` keeps it on one line and lets the heading take the free space. `level` is the heading's level (2 by default).
 */
withDefaults(
    defineProps<{
        headingId: string;
        meta?: string | null;
        fill?: boolean;
        level?: 2 | 3 | 4 | 5 | 6;
    }>(),
    { meta: null, fill: false, level: 2 },
);
</script>
<template>
    <header
        :class="[
            'border-border flex min-h-12 items-center gap-x-2.5 gap-y-1 border-b px-4 py-2.5',
            fill ? 'flex-nowrap' : 'flex-wrap',
        ]"
        data-widget-header
    >
        <div v-if="$slots.avatar" class="shrink-0" data-widget-avatar>
            <slot name="avatar" />
        </div>
        <div :class="['grid min-w-0 gap-0.5', fill && 'flex-1']">
            <component
                :is="`h${level}`"
                :id="headingId"
                class="m-0 min-w-0 text-sm font-semibold tracking-[-0.01em]"
                ><slot
            /></component>
            <div
                v-if="meta || $slots.meta"
                class="text-muted-foreground text-xs"
                data-widget-meta
            >
                <slot name="meta">{{ meta }}</slot>
            </div>
        </div>
        <slot name="badge" />
        <div
            v-if="$slots.actions"
            class="ms-auto flex items-center gap-2"
            data-widget-actions
        >
            <slot name="actions" />
        </div>
    </header>
</template>

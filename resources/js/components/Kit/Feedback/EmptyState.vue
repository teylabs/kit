<script setup lang="ts">
import {
    Activity,
    Calculator,
    CalendarDays,
    ClipboardList,
    Inbox,
    List,
    Search,
    Users,
} from '@lucide/vue';
import { type Component, computed, useId } from 'vue';
import { actionIcons } from '@/components/Kit/Action/actionIcons';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import { groundCard, useGroundSurface } from '@/components/Kit/Surface/surface';

/*
 * Nothing here yet: an icon, a title, a line of body and what to do next. The next step is the `action` slot (a kit Button) or server `actions` descriptors (an
 * ActionBar, whose `action` event is re-emitted). `compact` is the small form for a panel, a list or a table body; the default is centred for a whole page section.
 * It is a plain region labelled by its title, never a live region: it is there when the view renders, so announcing it would be noise. The `icon` slot replaces
 * the `icon` component, and the default slot the `body` text. Utilities and tokens only. On a tinted page ground it draws its own card (README, "Tinted page
 * grounds"), unless it already sits on one. `align` start puts it at the start edge (a dashboard widget's body); `variant` first-use gives it a dashed
 * border, for a place that has never had anything in it. `icon` also takes a name (a server descriptor's `icon`): one of the empty states' own or an action
 * icon; an unknown name draws no icon.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        body?: string | null;
        icon?: Component | string | null;
        actions?: ActionDescriptor[];
        compact?: boolean;
        level?: 2 | 3 | 4 | 5 | 6;
        align?: 'center' | 'start';
        variant?: 'default' | 'first-use';
    }>(),
    {
        body: null,
        icon: null,
        actions: () => [],
        compact: false,
        level: 3,
        align: 'center',
        variant: 'default',
    },
);
const emptyIcons: Record<string, Component> = {
    activity: Activity,
    assignments: ClipboardList,
    calculator: Calculator,
    calendar: CalendarDays,
    inbox: Inbox,
    list: List,
    people: Users,
    search: Search,
};
const iconComponent = computed<Component | null>(() =>
    typeof props.icon === 'string'
        ? (emptyIcons[props.icon] ?? actionIcons[props.icon] ?? null)
        : props.icon,
);
const start = computed(() => props.align === 'start');
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
// ActionBar hands back its own copy of a descriptor; the host gets the one it passed.
const original = (action: ActionDescriptor) =>
    (action.key
        ? props.actions.find((item) => item.key === action.key)
        : undefined) ?? action;
const titleId = `empty-state-${useId()}`;
const surface = useGroundSurface();
</script>
<template>
    <section
        :aria-labelledby="titleId"
        :class="[
            'grid min-w-0',
            start
                ? 'justify-items-start text-start'
                : 'justify-items-center text-center',
            compact ? 'gap-2 px-4 py-6' : 'gap-3 px-6 py-12',
            variant === 'first-use' &&
                'border-border rounded-lg border border-dashed',
            surface && groundCard,
        ]"
        :data-compact="compact || undefined"
        :data-align="start ? 'start' : undefined"
        :data-variant="variant === 'first-use' ? 'first-use' : undefined"
        :data-ground-surface="surface || undefined"
        data-empty-state
    >
        <div
            v-if="$slots.icon || iconComponent"
            :class="[
                'bg-muted text-muted-foreground flex items-center justify-center rounded-full',
                compact ? 'size-9' : 'size-12',
            ]"
            aria-hidden="true"
            data-empty-state-icon
        >
            <slot name="icon"
                ><component
                    :is="iconComponent"
                    :class="compact ? 'size-4' : 'size-6'"
            /></slot>
        </div>
        <component
            :is="`h${level}`"
            :id="titleId"
            :class="[
                'text-foreground m-0 font-semibold',
                compact ? 'text-sm' : 'text-base',
            ]"
            >{{ title }}</component
        >
        <div
            v-if="$slots.default || body"
            :class="[
                'text-muted-foreground max-w-prose',
                compact ? 'text-xs' : 'text-sm',
            ]"
            data-empty-state-body
        >
            <slot>{{ body }}</slot>
        </div>
        <div
            v-if="$slots.action || actions.length"
            :class="[
                'flex flex-wrap items-center gap-2',
                start ? 'justify-start' : 'justify-center',
                compact ? 'pt-1' : 'pt-2',
            ]"
            data-empty-state-action
        >
            <slot name="action" />
            <ActionBar
                v-if="actions.length"
                :actions="actions"
                @action="(action) => emit('action', original(action))"
            />
        </div>
    </section>
</template>

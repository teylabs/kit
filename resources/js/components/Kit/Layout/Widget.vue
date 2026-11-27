<script setup lang="ts">
import { computed, useId } from 'vue';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';
import ToneLabel from '@/components/Kit/Feedback/ToneLabel.vue';
import { groundCard, useGroundSurface } from '@/components/Kit/Surface/surface';
import WidgetHeader from './WidgetHeader.vue';

export interface WidgetEmpty {
    title: string;
    body?: string | null;
    icon?: string | null;
}
export interface WidgetDescriptor {
    key?: string;
    title: string;
    meta?: string | null;
    /** `planned` draws a dashed frame for a widget that is coming; `empty` draws `empty` in place of the body. */
    state?: 'ready' | 'empty' | 'planned' | null;
    empty?: WidgetEmpty | null;
    /** A count beside the title ("12 shown"). */
    count?: number | null;
    /** A quiet status at the header's end when there are no actions. */
    status?: { label: string; tone?: string | null } | null;
    headerVisible?: boolean;
}

/*
 * A dashboard widget frame (docs/widgets.md): a section on its own card (bg-card, so it reads on a tinted ground as well as a plain one) with a WidgetHeader
 * (title, avatar, meta, count badge, actions), then the body. `loading` draws a skeleton and marks the section busy; an `empty` state draws the kit's
 * EmptyState from `widget.empty`. The `actions` slot goes in the header, or in a footer with `action-placement="footer"`. `bare` drops the card and header
 * (the widget sits inside something that already frames it); the section is then labelled by the title. Slots: title, avatar, meta, badge, actions, default.
 */
const props = withDefaults(
    defineProps<{
        widget: WidgetDescriptor;
        loading?: boolean;
        actionPlacement?: 'header' | 'footer';
        bare?: boolean;
        level?: 2 | 3 | 4 | 5 | 6;
    }>(),
    { loading: false, actionPlacement: 'header', bare: false, level: 2 },
);
const headingId = `widget-${useId()}`;
const headed = computed(
    () => !props.bare && props.widget.headerVisible !== false,
);
const state = computed(() => props.widget.state ?? 'ready');
const surface = useGroundSurface(() => !props.bare);
</script>
<template>
    <section
        :aria-labelledby="headed ? headingId : undefined"
        :aria-label="headed ? undefined : widget.title"
        :aria-busy="loading || undefined"
        :class="[
            'grid h-full min-w-0 content-start',
            bare
                ? 'gap-3'
                : [
                      'bg-card text-card-foreground border-border overflow-hidden rounded-xl border',
                      state === 'planned' && 'border-dashed',
                  ],
            surface && !bare && groundCard,
        ]"
        :data-widget="widget.key"
        :data-widget-state="loading ? 'loading' : state"
        :data-bare="bare || undefined"
    >
        <WidgetHeader
            v-if="headed"
            :heading-id="headingId"
            :meta="widget.meta"
            :level="level"
        >
            <slot name="title">{{ widget.title }}</slot>
            <template v-if="$slots.avatar" #avatar>
                <slot name="avatar" />
            </template>
            <template v-if="$slots.meta" #meta><slot name="meta" /></template>
            <template v-if="$slots.badge || widget.count != null" #badge>
                <slot name="badge">
                    <span
                        class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums"
                        :aria-label="`${widget.count} shown`"
                        data-widget-count
                        >{{ widget.count }}</span
                    >
                </slot>
            </template>
            <template
                v-if="
                    ($slots.actions && actionPlacement === 'header') ||
                    (!$slots.actions && widget.status)
                "
                #actions
            >
                <slot v-if="$slots.actions" name="actions" />
                <ToneLabel
                    v-else-if="widget.status"
                    :label="widget.status.label"
                    :tone="widget.status.tone"
                    data-widget-status
                />
            </template>
        </WidgetHeader>
        <div
            :class="['grid min-w-0 content-start gap-3', !bare && 'px-4 py-3']"
            data-widget-body
        >
            <div
                v-if="loading"
                role="status"
                class="grid gap-3 motion-safe:animate-pulse"
                data-widget-loading
            >
                <span class="sr-only">Loading {{ widget.title }}</span>
                <div
                    v-for="line in 3"
                    :key="line"
                    class="bg-muted h-10 rounded-md"
                />
            </div>
            <EmptyState
                v-else-if="state === 'empty' && widget.empty"
                :title="widget.empty.title"
                :body="widget.empty.body"
                :icon="widget.empty.icon"
                :level="level < 6 ? ((level + 1) as 3 | 4 | 5 | 6) : 6"
                compact
                align="start"
            />
            <slot v-else />
        </div>
        <footer
            v-if="$slots.actions && (actionPlacement === 'footer' || !headed)"
            class="border-border flex justify-end gap-2 border-t px-4 py-3"
            data-widget-footer
        >
            <slot name="actions" />
        </footer>
    </section>
</template>

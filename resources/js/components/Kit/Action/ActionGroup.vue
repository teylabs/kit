<script setup lang="ts">
import { Primitive, type PrimitiveProps } from 'reka-ui';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/components/Kit/utils';

// A form's own actions (Buttons, links styled as buttons) as one group with one gap (12px) (docs/action-group.md). `direction="row"`, the default: one row that
// wraps, in the order written. `direction="column"`, for an auth-style card (sign in, two-factor, verify email, an invitation): stacked, every action the group's
// full width, in the order written, so the primary action goes first; the same at every breakpoint (no `sm:` or `md:` switch). Layout only: it never styles
// its children's appearance, runs nothing and adds no role, so each control keeps its own name and tab order.
const props = withDefaults(
    defineProps<
        PrimitiveProps & {
            direction?: 'row' | 'column';
            class?: HTMLAttributes['class'];
        }
    >(),
    { as: 'div', direction: 'row', class: undefined },
);
const classes = computed(() =>
    cn(
        'flex min-w-0 gap-3',
        props.direction === 'column'
            ? 'flex-col items-stretch *:w-full'
            : 'flex-wrap items-center',
        props.class,
    ),
);
</script>
<template>
    <Primitive
        :as="props.as"
        :as-child="props.asChild"
        :class="classes"
        data-slot="action-group"
        :data-direction="props.direction"
        ><slot
    /></Primitive>
</template>

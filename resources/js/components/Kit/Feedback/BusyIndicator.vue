<script setup lang="ts">
/*
 * An inline busy mark: a spinning circle (still under reduced motion) followed by the default slot as its label. `$attrs` go on the svg, so a
 * caller sizes or colours it there. The svg is aria-hidden; the label slot is what a screen reader hears. Also exported as Spinner. Hook:
 * `data-busy-indicator` on the svg.
 */
defineOptions({ inheritAttrs: false });
</script>
<template>
    <span class="inline-flex items-center gap-1" data-slot="busy-indicator">
        <svg
            v-bind="$attrs"
            aria-hidden="true"
            focusable="false"
            viewBox="0 0 16 16"
            class="size-4 shrink-0 animate-spin motion-reduce:animate-none"
            data-busy-indicator
        >
            <circle
                cx="8"
                cy="8"
                r="6"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                opacity=".25"
            />
            <path
                d="M8 2a6 6 0 0 1 6 6"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
            />
        </svg>
        <slot />
    </span>
</template>

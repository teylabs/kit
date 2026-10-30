<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Primitive, type PrimitiveProps } from 'reka-ui';
import { cn } from './cn';
import { buttonVariants, type ButtonVariants } from '.';

// The host's shadcn-vue Button (new-york-v4), as a starter kit generates it, so kit components that wrap it render its real variant classes here.
interface Props extends PrimitiveProps {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    class?: HTMLAttributes['class'];
}
const props = withDefaults(defineProps<Props>(), { as: 'button' });
</script>
<template>
    <Primitive data-slot="button" :as="as" :as-child="asChild" :class="cn(buttonVariants({ variant, size }), props.class)"><slot /></Primitive>
</template>

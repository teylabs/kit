<script setup lang="ts">
// A person's photo, or their initials when they have none. `src2x` is the retina source. Decorative by default (the name is beside it).
// `size`: xs (20px, beside a line of small text), sm (28px), md (32px, the default), lg (40px) or xl (80px, the profile size, which `large` also gives).
const props = defineProps<{
    initials: string;
    src?: string | null;
    src2x?: string | null;
    large?: boolean;
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
}>();
const sizes = {
    xs: 'size-5 text-[10px]',
    sm: 'size-7 text-xs',
    md: 'size-8 text-xs',
    lg: 'size-10 text-sm',
    xl: 'size-20 text-xl',
};
</script>
<template>
    <span
        aria-hidden="true"
        :class="[
            'bg-accent text-accent-foreground flex shrink-0 items-center justify-center overflow-hidden rounded-full font-semibold',
            sizes[props.size ?? (props.large ? 'xl' : 'md')],
        ]"
        data-slot="avatar"
    >
        <img
            v-if="src"
            :src="src"
            :srcset="src2x ? `${src} 1x, ${src2x} 2x` : undefined"
            alt=""
            class="size-full object-cover"
        />
        <template v-else>{{ initials }}</template>
    </span>
</template>

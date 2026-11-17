<script setup lang="ts">
import { computed } from 'vue';
import { avatarColor } from './avatarColor';

// A person's photo, or their initials when they have none. `src2x` is the retina source. Decorative by default (the name is beside it).
// The initials sit on the person's colour (docs/avatars.md): `color` (the server's AvatarColor, sent as `avatar_color`) or, without it, one derived from
// `identity` (their user id). With neither, the neutral accent disc.
// `ring` draws a white ring around it, so any colour (and a photo) reads on a dark bar as well as a light page: the shell's account avatar uses it.
// `size`: xs (20px, beside a line of small text), sm (28px), md (32px, the default), lg (40px) or xl (80px, the profile size, which `large` also gives).
const props = defineProps<{
    initials: string;
    src?: string | null;
    src2x?: string | null;
    large?: boolean;
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
    color?: string | null;
    identity?: string | number | null;
    ring?: boolean;
}>();
const disc = computed(() =>
    props.color || (props.identity !== null && props.identity !== undefined)
        ? avatarColor(props.identity ?? '', props.color)
        : null,
);
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
            disc
                ? 'bg-(--avatar-color) text-white'
                : 'bg-accent text-accent-foreground',
            'flex shrink-0 items-center justify-center overflow-hidden rounded-full font-semibold',
            sizes[props.size ?? (props.large ? 'xl' : 'md')],
            ring && 'ring-2 ring-white',
        ]"
        :data-avatar-ring="ring || undefined"
        :style="disc ? { '--avatar-color': disc } : undefined"
        :data-avatar-color="disc ?? undefined"
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

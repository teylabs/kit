<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { avatarColor, avatarInk } from './avatarColor';

// A person's photo, or their initials when they have none. `src2x` is the retina source. Decorative by default (the name is beside it).
// The initials sit on the person's colour (docs/avatars.md): `color` (the server's AvatarColor, sent as `avatar_color`) or, without it, one derived from
// `identity` (their user id). With neither, the neutral accent disc.
// The initials are white or black, whichever reads better on the colour (avatarInk: every palette colour takes white; a light stored one takes black).
// A gap ring (2px, a box-shadow, so it never moves the layout) in the colour of the surface it sits on: `--avatar-ring`, which the kit's surfaces set (a card,
// a menu, a popover, a dialog or modal, the shell's bar), falling back to the page background. It parts overlapping avatars and keeps a disc off the surface's
// edge. On by default; `:ring="false"` leaves it off.
// A photo that fails to load (a stale page, a file lost since) shows the initials instead of the browser's broken-image icon; a new `src` tries again.
// `size`: xs (20px, beside a line of small text), sm (28px), md (32px, the default), lg (40px) or xl (80px, the profile size, which `large` also gives).
const props = withDefaults(
    defineProps<{
        initials: string;
        src?: string | null;
        src2x?: string | null;
        large?: boolean;
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
        color?: string | null;
        identity?: string | number | null;
        ring?: boolean;
    }>(),
    { ring: true },
);
const disc = computed(() =>
    props.color || (props.identity !== null && props.identity !== undefined)
        ? avatarColor(props.identity ?? '', props.color)
        : null,
);
const failed = ref(false);
watch(
    () => props.src,
    () => (failed.value = false),
);
const step = computed(() => props.size ?? (props.large ? 'xl' : 'md'));
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
                ? 'bg-(--avatar-color) text-(--avatar-ink)'
                : 'bg-accent text-accent-foreground',
            'flex shrink-0 items-center justify-center overflow-hidden rounded-full font-semibold',
            sizes[step],
            ring && 'ring-2 ring-[var(--avatar-ring,var(--background))]',
        ]"
        :data-avatar-ring="ring || undefined"
        :data-avatar-size="step"
        :style="
            disc
                ? { '--avatar-color': disc, '--avatar-ink': avatarInk(disc) }
                : undefined
        "
        :data-avatar-color="disc ?? undefined"
        data-slot="avatar"
    >
        <img
            v-if="src && !failed"
            :src="src"
            :srcset="src2x ? `${src} 1x, ${src2x} 2x` : undefined"
            alt=""
            class="size-full object-cover"
            @error="failed = true"
        />
        <template v-else>{{ initials }}</template>
    </span>
</template>

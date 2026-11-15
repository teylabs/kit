<script setup lang="ts">
import { computed, useId, useSlots } from 'vue';
import Avatar from '@/components/Kit/Layout/Avatar.vue';
import { accentClass, type CommentAuthor } from './comments';

/*
 * The surface a comment or note sits on (docs/comments.md): a card with an optional colour accent on its left or top edge, a header line (avatar, author, the
 * `timestamp` slot, the colour's `meaning`, and the `actions` slot on the right), the body (default slot) and a `footer` slot. Presentational: it renders what it
 * is given and does nothing. `accent` is a colour ({tone, label, meaning?}); its label is read out with the author, so a colour never carries meaning alone.
 * `variant` compact is one line: the body truncated, the time after it, no header. `density`: default (a bordered card), compact (no card, an inset row) or flush
 * (a filled tile that fills its cell). `accentWidth`: thin, medium (default) or wide. `authorStyle`: avatar-name (default), name or none. `as` is its element.
 */
const props = withDefaults(
    defineProps<{
        accent?: {
            tone: string;
            label: string;
            meaning?: string | null;
        } | null;
        author?: CommentAuthor | null;
        variant?: 'full' | 'compact';
        density?: 'default' | 'compact' | 'flush';
        accentEdge?: 'left' | 'top';
        accentWidth?: 'thin' | 'medium' | 'wide';
        authorStyle?: 'avatar-name' | 'name' | 'none';
        as?: 'article' | 'div' | 'li';
    }>(),
    {
        accent: null,
        author: null,
        variant: 'full',
        density: 'default',
        accentEdge: 'left',
        accentWidth: 'medium',
        authorStyle: 'avatar-name',
        as: 'article',
    },
);
const slots = useSlots();
const id = useId();
const widths = {
    left: { thin: 'border-l-2', medium: 'border-l-4', wide: 'border-l-8' },
    top: { thin: 'border-t-2', medium: 'border-t-4', wide: 'border-t-8' },
};
const showsAuthor = computed(
    () => props.authorStyle !== 'none' && Boolean(props.author),
);
const header = computed(
    () =>
        props.variant !== 'compact' &&
        (showsAuthor.value ||
            Boolean(slots.timestamp) ||
            Boolean(slots.actions) ||
            Boolean(props.accent?.meaning)),
);
</script>
<template>
    <component
        :is="as"
        :class="[
            'text-card-foreground grid min-w-0 content-start',
            variant === 'compact' ? 'gap-1' : 'gap-3',
            density === 'flush'
                ? 'bg-card h-full p-3'
                : density === 'compact'
                  ? 'py-3 pl-3'
                  : 'border-border bg-card rounded-lg border p-4',
            accent && [
                widths[accentEdge][accentWidth],
                accentClass(accent.tone, accentEdge),
            ],
        ]"
        :aria-labelledby="as === 'article' && header ? `${id}-meta` : undefined"
        :data-note-card="density"
        :data-note-variant="variant"
        :data-accent-edge="accent ? accentEdge : undefined"
        :data-tone="accent?.tone"
    >
        <header
            v-if="header"
            class="flex min-w-0 items-center gap-2"
            data-note-header
        >
            <p
                :id="`${id}-meta`"
                class="text-muted-foreground flex min-w-0 flex-1 items-center gap-1.5 text-xs whitespace-nowrap"
                data-note-meta
            >
                <Avatar
                    v-if="showsAuthor && authorStyle === 'avatar-name'"
                    :initials="author!.initials"
                    :src="author!.avatar_url"
                    :src2x="author!.avatar_url_2x"
                    size="xs"
                />
                <span
                    v-if="showsAuthor"
                    class="text-foreground min-w-0 truncate font-medium"
                    data-note-author
                    >{{ author!.name }}</span
                >{{ ' ' }}
                <span v-if="$slots.timestamp" class="shrink-0"
                    ><slot name="timestamp" /></span
                >{{ ' ' }}
                <span v-if="accent" class="sr-only" data-note-colour-name>{{
                    accent.label
                }}</span
                >{{ ' ' }}
                <span
                    v-if="accent?.meaning"
                    class="shrink-0"
                    data-note-meaning
                    >{{ accent.meaning }}</span
                >
            </p>
            <div v-if="$slots.actions" class="text-muted-foreground shrink-0">
                <slot name="actions" />
            </div>
        </header>
        <div
            v-if="variant === 'compact'"
            class="flex min-w-0 items-center gap-3 text-sm"
        >
            <div class="min-w-0 flex-1 truncate"><slot /></div>
            <span v-if="accent" class="sr-only">{{ accent.label }}</span>
            <span
                v-if="$slots.timestamp"
                class="text-muted-foreground shrink-0 text-xs"
                ><slot name="timestamp"
            /></span>
        </div>
        <div v-else class="grid min-w-0 gap-3"><slot /></div>
        <slot name="footer" />
    </component>
</template>

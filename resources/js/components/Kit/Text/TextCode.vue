<script setup lang="ts">
import { Check, Copy, WrapText } from '@lucide/vue';
import { computed, ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import Card from '@/components/Kit/Card/Card.vue';
import { CARD_GUTTER } from '@/components/Kit/Card/cardTone';
import { useCopy } from './useCopy';

/*
 * Raw text kept exactly as written, in a monospaced <pre> (docs/text.md): code, a JSON payload, a log, an email's source. Pass `content` (whitespace kept
 * byte for byte, never parsed as markup), or the default slot. Its height is bounded by `maxHeight` (sm 12rem, md 20rem, the default, lg 32rem, or none) and
 * it scrolls inside, as a region that takes focus, named by `title` (else "Code"), so a keyboard can scroll it. Long lines wrap by default; `wrap` false
 * scrolls them sideways. `card` frames it in a kit Card with `title` as its heading (at `level`, 3 by default) and a toolbar: Copy (the whole text, through the kit's clipboard helper,
 * spoken once copied) and a Wrap toggle (aria-pressed). `toolbar` shows the same toolbar without the card. The text is never cut: what scrolls out of view
 * is still selected, copied and read.
 */
const props = withDefaults(
    defineProps<{
        content?: string | number | null;
        title?: string;
        card?: boolean;
        toolbar?: boolean;
        maxHeight?: 'sm' | 'md' | 'lg' | 'none';
        wrap?: boolean;
        level?: 1 | 2 | 3 | 4 | 5 | 6;
    }>(),
    {
        content: null,
        title: undefined,
        card: false,
        toolbar: false,
        maxHeight: 'md',
        wrap: true,
        level: 3,
    },
);
// Whole class strings, never built from the prop (Tailwind finds classes by scanning the source).
const heights = {
    sm: 'max-h-48',
    md: 'max-h-80',
    lg: 'max-h-128',
    none: '',
} as const;
const pre = ref<HTMLElement | null>(null);
const wrapped = ref(props.wrap);
const tools = computed(() => props.card || props.toolbar);
const { state, copy } = useCopy(() =>
    props.content !== null && props.content !== undefined
        ? String(props.content)
        : (pre.value?.textContent ?? ''),
);
const status = computed(() =>
    state.value === 'copied'
        ? 'Copied.'
        : state.value === 'failed'
          ? 'Could not copy. Select the text to copy it, or try again.'
          : '',
);
</script>
<template>
    <component
        :is="card ? Card : 'div'"
        :class="['min-w-0', !card && tools && 'grid gap-2']"
        data-slot="text-code"
        :data-wrap="String(wrapped)"
    >
        <div
            v-if="tools"
            :class="[
                'flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1',
                card && [CARD_GUTTER, 'py-2'],
            ]"
            data-text-code-tools
        >
            <component
                :is="`h${level}`"
                v-if="card && title"
                class="min-w-0 truncate text-base font-semibold"
                >{{ title }}</component
            >
            <span class="ms-auto flex items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :aria-pressed="wrapped"
                    data-text-code-wrap
                    @click="wrapped = !wrapped"
                    ><WrapText class="size-4" aria-hidden="true" /> Wrap</Button
                >
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="state === 'copying'"
                    :data-state="state"
                    data-text-code-copy
                    @click="copy"
                    ><Check
                        v-if="state === 'copied'"
                        class="size-4"
                        aria-hidden="true"
                    /><Copy v-else class="size-4" aria-hidden="true" />
                    Copy</Button
                >
                <span role="status" aria-live="polite" class="sr-only">{{
                    status
                }}</span>
            </span>
        </div>
        <pre
            ref="pre"
            role="region"
            :aria-label="title ?? 'Code'"
            tabindex="0"
            :class="[
                // w-0 min-w-full: full width, but no min-content of its own, so a long line never widens a grid or flex parent.
                'text-foreground focus-visible:outline-ring w-0 min-w-full overflow-auto font-mono text-xs/5 focus-visible:outline-2 focus-visible:-outline-offset-2',
                card ? [CARD_GUTTER, 'py-3'] : 'bg-muted rounded-md p-3',
                heights[maxHeight],
                wrapped ? 'break-words whitespace-pre-wrap' : 'whitespace-pre',
            ]"
            data-text-code-body
        ><template v-if="content !== null && content !== undefined">{{ content }}</template><slot v-else /></pre>
    </component>
</template>

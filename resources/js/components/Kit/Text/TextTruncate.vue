<script setup lang="ts">
import { onBeforeUnmount, onMounted, onUpdated, ref } from 'vue';
import { contentWidth } from '@/components/Kit/Data/middleTruncate';
import { CLAMP_LINES, type ClampLines } from './text';

/*
 * Text cut at its end to fit (docs/text.md): one line with an ellipsis (`lines` 1, the default), or up to six lines clamped with one (`lines` 2 to 6). For a
 * file name, path or URL, whose end matters, use MiddleTruncate. `text` or the default slot; `maxWidth` (a CSS length, `16rem`) bounds it, else it takes the
 * width it is given (a flex child needs `min-w-0`). The whole text stays in the element, so the accessible name, a selection and a copy are all of it, and
 * once mounted it measures whether the end was cut, with the same content-box measure as MiddleTruncate, and only then puts the whole text in `title`,
 * marking `data-truncated`. It measures again when its box resizes. On the server it is the CSS truncation alone, with no title, observer or timer.
 */
const props = withDefaults(
    defineProps<{
        text?: string | null;
        lines?: ClampLines;
        maxWidth?: string;
    }>(),
    { text: null, lines: 1, maxWidth: undefined },
);
const root = ref<HTMLElement | null>(null);
const cut = ref(false);
const full = ref('');
let observer: ResizeObserver | null = null;

function measure(): void {
    const el = root.value;
    if (!el) return;
    full.value = (props.text ?? el.textContent ?? '').trim();
    if (props.lines > 1) {
        cut.value = el.scrollHeight - el.clientHeight > 1;
        return;
    }
    const text = document.createRange();
    text.selectNodeContents(el);
    cut.value =
        text.getBoundingClientRect().width >
        contentWidth(el, getComputedStyle(el)) + 0.5;
}

onMounted(() => {
    measure();
    if (typeof ResizeObserver !== 'undefined' && root.value) {
        observer = new ResizeObserver(measure);
        observer.observe(root.value);
    }
});
onUpdated(measure);
onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
});
</script>
<template>
    <span
        ref="root"
        :title="cut ? full : undefined"
        :class="[
            'min-w-0 break-words',
            lines > 1 ? CLAMP_LINES[lines] : 'block truncate',
            maxWidth && 'max-w-(--kit-text-max-width)',
        ]"
        :style="maxWidth ? { '--kit-text-max-width': maxWidth } : undefined"
        data-slot="text-truncate"
        :data-truncated="cut ? '' : undefined"
        ><slot>{{ text }}</slot></span
    >
</template>

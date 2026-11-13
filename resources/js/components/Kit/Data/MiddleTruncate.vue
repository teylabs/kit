<script setup lang="ts">
import {
    computed,
    onBeforeUnmount,
    onMounted,
    onUpdated,
    ref,
    watch,
} from 'vue';
import {
    type MiddleTruncateKind,
    canvasFont,
    middleTruncate,
} from './middleTruncate';

/*
 * A long file name, path or URL cut in the middle to fit its box, so its start and its tail stay visible: `paraframe-brand…final.pdf`, not
 * `paraframe-brand-guidel…` (docs/middle-truncate.md). A file name keeps its whole extension; a path or URL its last segment, a URL the start of its host.
 * `kind`: `file`, `path`, `url` or `auto` (the default, read from the value); `min-tail` keeps at least that many characters at the end. It fits the width
 * it is given, measured with a canvas in the element's own font and again on every resize, never a fixed character count. On the server and before it
 * mounts it is the whole value with a CSS end ellipsis. The cut middle stays in the text at zero size, so the accessible name, a selection and a copy are
 * the whole value; `title` shows it on hover. A block element: put it where the width is bounded (a flex child with `min-w-0`, a table cell).
 */
const props = withDefaults(
    defineProps<{
        value: string;
        kind?: MiddleTruncateKind;
        minTail?: number;
    }>(),
    { kind: 'auto', minTail: 0 },
);
const root = ref<HTMLElement | null>(null);
// The content box's width and the font it was measured in; null until mounted, so the server and the first render show the whole value.
const box = ref<{ width: number; font: string } | null>(null);
// Pixels a cut still overflowed by once painted: the browser shapes each part on its own (an Arabic letter's joining form at the cut, kerning across it),
// which a canvas measuring the joined string can't see. Taken off the width until the box or the font changes.
const slack = ref(0);
let context: CanvasRenderingContext2D | null = null;
let observer: ResizeObserver | null = null;
// Bumped when a web font finishes loading, which changes every width.
let fontsLoaded = 0;
const fontsDone = () => {
    fontsLoaded++;
    update();
};

// The unrounded content box: clientWidth and scrollWidth round, and a fraction of a pixel over is enough for the CSS ellipsis.
function contentWidth(el: HTMLElement, style: CSSStyleDeclaration): number {
    return (
        el.getBoundingClientRect().width -
        parseFloat(style.paddingLeft) -
        parseFloat(style.paddingRight) -
        parseFloat(style.borderLeftWidth) -
        parseFloat(style.borderRightWidth)
    );
}

function update(): void {
    const el = root.value;
    if (!el || !context) {
        return;
    }
    const style = getComputedStyle(el);
    const width = Math.floor(contentWidth(el, style));
    const font = `${canvasFont(style)}|${style.letterSpacing}|${fontsLoaded}`;
    if (box.value?.width !== width || box.value.font !== font) {
        box.value = { width, font };
        slack.value = 0;
    }
}

// The correction belongs to the text it was measured on: a new value, kind or minTail starts again from none.
watch(
    () => [props.value, props.kind, props.minTail],
    () => {
        slack.value = 0;
    },
);

const parts = computed(() => {
    const el = root.value;
    if (!box.value || !context || !el) {
        return null;
    }
    const style = getComputedStyle(el);
    context.font = canvasFont(style);
    if ('letterSpacing' in context) {
        context.letterSpacing =
            style.letterSpacing === 'normal' ? '0px' : style.letterSpacing;
    }
    const canvas = context;

    return middleTruncate(
        props.value,
        box.value.width - slack.value,
        (text) => canvas.measureText(text).width,
        { kind: props.kind, minTail: props.minTail },
    );
});

onMounted(() => {
    context = document.createElement('canvas').getContext('2d');
    if (!context || !root.value) {
        return;
    }
    update();
    if (typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(update);
        observer.observe(root.value);
    }
    document.fonts?.ready.then(fontsDone);
    document.fonts?.addEventListener('loadingdone', fontsDone);
});
onUpdated(() => {
    const el = root.value;
    if (!el || !box.value || !parts.value?.truncated) {
        return;
    }
    const text = document.createRange();
    text.selectNodeContents(el);
    const over = Math.ceil(
        text.getBoundingClientRect().width -
            contentWidth(el, getComputedStyle(el)),
    );
    if (over > 0 && slack.value < box.value.width) {
        slack.value += over;
    }
});
onBeforeUnmount(() => {
    document.fonts?.removeEventListener('loadingdone', fontsDone);
    observer?.disconnect();
    observer = null;
    context = null;
});
</script>

<template>
    <span
        ref="root"
        :title="value"
        dir="auto"
        data-slot="middle-truncate"
        :data-truncated="parts?.truncated ? '' : undefined"
        class="block min-w-0 truncate"
    >
        <template v-if="parts?.truncated">
            <span>{{ parts.head }}</span>
            <template v-if="parts.middle">
                <span
                    aria-hidden="true"
                    data-ellipsis="…"
                    class="before:content-[attr(data-ellipsis)]"
                ></span>
                <span data-cut class="text-[0px] tracking-normal">{{
                    parts.middle
                }}</span>
            </template>
            <span>{{ parts.tail }}</span>
            <template v-if="parts.end">
                <span
                    aria-hidden="true"
                    data-ellipsis="…"
                    class="before:content-[attr(data-ellipsis)]"
                ></span>
                <span data-cut class="text-[0px] tracking-normal">{{
                    parts.end
                }}</span>
            </template>
        </template>
        <template v-else>{{ value }}</template>
    </span>
</template>

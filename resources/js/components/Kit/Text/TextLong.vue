<script setup lang="ts">
import { computed, useId } from 'vue';
import { longTextCut } from './text';

/*
 * A long plain-text body (a note, a comment, a description) cut to `limit` characters, with a "Show more" button that shows it whole (docs/text.md). The cut
 * counts characters as a reader does (an emoji or an accented letter is one, never split), and its line breaks are kept. The button is a real button with
 * aria-expanded and aria-controls, after the text; `v-model:expanded` (or `expanded`) starts it open. `collapsible` false always shows it whole. Text only,
 * never HTML. `moreLabel` and `lessLabel` replace the button's words.
 */
const props = withDefaults(
    defineProps<{
        text: string;
        limit?: number;
        collapsible?: boolean;
        moreLabel?: string;
        lessLabel?: string;
    }>(),
    {
        limit: 360,
        collapsible: true,
        moreLabel: 'Show more',
        lessLabel: 'Show less',
    },
);
const expanded = defineModel<boolean>('expanded', { default: false });
const id = useId();
const cut = computed(() => longTextCut(props.text ?? '', props.limit));
const truncated = computed(() => props.collapsible && cut.value.truncated);
const visible = computed(() =>
    truncated.value && !expanded.value ? cut.value.shown : (props.text ?? ''),
);
</script>
<template>
    <p
        class="break-words whitespace-pre-wrap"
        data-slot="text-long"
        :data-expanded="truncated ? String(expanded) : undefined"
    >
        <span :id="id" data-text-long-content>{{ visible }}</span>
        <!-- prettier-ignore -->
        <template v-if="truncated">{{ ' ' }}<button type="button" class="text-muted-foreground hover:text-foreground focus-visible:outline-ring inline rounded-sm text-xs underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2" :aria-expanded="expanded" :aria-controls="id" data-text-long-toggle @click="expanded = !expanded">{{ expanded ? lessLabel : moreLabel }}</button></template>
    </p>
</template>

<script setup lang="ts">
import { PROSE, TEXT_SIZES, type TextSize } from './text';

/*
 * Running text for reading (docs/text.md): a note, a description, a help page. `text` is plain text, its line breaks kept and long words broken, never
 * markup. The default slot takes markup the app renders itself (paragraphs, lists, headings, links, quotes, code, tables) and styles it from the kit's
 * tokens, with no typography plugin and no outer margin. `size` is sm (the default), base or lg. It never renders HTML from a string: sanitize and render
 * that yourself, or use InputRichText's document.
 */
withDefaults(defineProps<{ text?: string | null; size?: TextSize }>(), {
    text: null,
    size: 'sm',
});
</script>
<template>
    <div
        :class="['max-w-none text-pretty break-words', TEXT_SIZES[size], PROSE]"
        data-slot="text-prose"
    >
        <p v-if="text !== null" class="whitespace-pre-wrap">{{ text }}</p>
        <slot v-else />
    </div>
</template>

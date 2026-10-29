<script setup lang="ts">
// RichTextView on a canvas and in a preview, as a host would use it: a root element and class, allow and families, and the content-error event. Compiled by
// bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import RichTextView from '@/components/Kit/Form/RichTextView.vue';
import { richTextView, type ContentError, type RichDocument, type RichViewNode } from '@/components/Kit/Form/richText';

const slide = ref<RichDocument | null>({ type: 'doc', content: [{ type: 'paragraph', attrs: { textAlign: 'center' }, content: [{ type: 'text', text: 'Big', marks: [{ type: 'textStyle', attrs: { fontSize: 2.5, fontFamily: 'display' } }] }] }] });
const failed = ref<ContentError | null>(null);
const nodes: RichViewNode[] = richTextView(slide.value, null, ['textAlign', 'textStyle'], ['display', 'sans']);
</script>
<template>
    <RichTextView :document="slide" as="section" class="text-[--slide-ink]" :allow="['textAlign', 'textStyle']" :families="['display', 'sans']" @content-error="error => (failed = error)" />
    <RichTextView :document="null" />
    <!-- @vue-expect-error `document` is required -->
    <RichTextView />
    <span>{{ failed?.message }}{{ nodes.length }}</span>
</template>

<script setup lang="ts">
import TextCode from './TextCode.vue';

// DEPRECATED: RawOutputCard is now TextCode with `card` (docs/text.md): raw output in a Card under `title`, with a bounded height, Copy and a Wrap toggle.
// This alias renders that and is removed in the next minor release; use <TextCode card>.
withDefaults(
    defineProps<{ title?: string; content?: string | number | null }>(),
    { title: undefined, content: null },
);
</script>
<template>
    <TextCode card :title="title" :content="content"><slot /></TextCode>
</template>

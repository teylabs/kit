<script setup lang="ts">
// Textarea's defaultValue, hasFocus and autosize, and its exposed focus(). Compiled by bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import Textarea from '@/components/Kit/Form/Textarea.vue';

const box = ref<InstanceType<typeof Textarea>>();
const focusIt = () => box.value?.focus();
</script>
<template>
    <Textarea ref="box" default-value="Start" has-focus autosize />
    <button type="button" @click="focusIt">Focus</button>
</template>

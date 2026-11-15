<script setup lang="ts">
import { computed, provide } from 'vue';
import { rowErrorsKey, rowPathKey } from './repeaterState';
const props = defineProps<{ errors: Record<string, string>; path: string }>();
provide(
    rowErrorsKey,
    computed(() => props.errors),
);
provide(
    rowPathKey,
    computed(() => props.path),
);
</script>
<template><slot /></template>

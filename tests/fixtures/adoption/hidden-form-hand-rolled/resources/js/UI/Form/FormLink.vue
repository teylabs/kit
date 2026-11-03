<script setup lang="ts">
import { ref } from 'vue';

defineProps<{ url: string }>();
const form = ref<HTMLFormElement | null>(null);
defineExpose({ submit: () => form.value?.submit() });
</script>
<template>
    <Teleport to="body"><form ref="form" :action="url" method="GET" target="_blank"><input type="hidden" name="id" value="1"></form></Teleport>
</template>

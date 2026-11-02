<script setup lang="ts">
import VueDatePicker from '@vuepic/vue-datepicker';
defineProps<{ name: string }>();
const model = defineModel<string>();
</script>
<template><VueDatePicker v-model="model" model-type="yyyy-MM-dd" /><input type="hidden" :name="name" :value="model" /></template>

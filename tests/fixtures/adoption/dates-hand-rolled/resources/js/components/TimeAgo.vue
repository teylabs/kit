<script setup lang="ts">
import { format } from 'timeago.js';
defineProps<{ value: string }>();
</script>
<template><span :title="value">{{ format(value) }}</span></template>

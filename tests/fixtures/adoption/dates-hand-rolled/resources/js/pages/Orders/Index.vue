<script setup lang="ts">
import TimeAgo from '@/components/TimeAgo.vue';
defineProps<{ orders: { id: number; created_at: string }[] }>();
</script>
<template><ul><li v-for="order in orders" :key="order.id">#{{ order.id }} <TimeAgo :value="order.created_at" /></li></ul></template>

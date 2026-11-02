<script setup lang="ts">
import { DateTime } from '@/components/Kit';
defineProps<{ orders: { id: number; created_at: string; due_on: string }[] }>();
</script>
<template><ul><li v-for="order in orders" :key="order.id">#{{ order.id }} <DateTime :value="order.created_at" format="relative" /> due <DateTime :value="order.due_on" format="date" /></li></ul></template>

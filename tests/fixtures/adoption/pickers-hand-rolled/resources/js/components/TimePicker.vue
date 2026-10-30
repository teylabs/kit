<script setup lang="ts">
defineProps<{ name: string }>();
</script>
<template><input type="time" :name="name" /></template>

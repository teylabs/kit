<template>
  <div class="rounded border p-4">{{ score }}</div>
</template>

<script setup lang="ts">
defineProps<{ score: number }>();
</script>

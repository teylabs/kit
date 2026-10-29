<script setup>
import draggable from 'vuedraggable';
</script>

<template>
  <draggable :list="tasks" item-key="id" />
</template>

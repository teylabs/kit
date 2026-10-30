<script setup>
import draggable from 'vuedraggable';
</script>

<template>
  <draggable :list="fields" item-key="id" />
</template>

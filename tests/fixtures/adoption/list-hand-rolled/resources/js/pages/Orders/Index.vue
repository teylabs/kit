<script setup>
import { RecycleScroller } from 'vue-virtual-scroller';
import Pagination from '@/components/Pagination.vue';
</script>
<template>
  <RecycleScroller :items="[]" :item-size="32" />
  <Pagination />
</template>

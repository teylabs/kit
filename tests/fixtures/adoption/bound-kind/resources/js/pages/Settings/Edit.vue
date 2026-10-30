<script setup>
import { InputNumber } from '@/components/Kit';
const kind = 'decimal';
</script>

<template>
  <form>
    <InputNumber name="limit" label="Limit" :kind="kind" />
  </form>
</template>

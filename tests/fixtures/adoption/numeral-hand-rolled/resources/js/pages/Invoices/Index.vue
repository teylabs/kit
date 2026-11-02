<script setup>
import numeral from 'numeral';
const total = (v) => numeral(v).format('$0,0.00');
</script>

<template>
  <p>{{ total(1234.5) }}</p>
</template>

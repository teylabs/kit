<script setup lang="ts">
import { InputDate, InputDateRange, InputTime } from '@/components/Kit';
</script>
<template><form method="post"><InputDate name="arrives_on" label="Arrives" /><InputDateRange name="stay" label="Stay" /><InputTime name="arrives_at" label="Arrival time" /></form></template>

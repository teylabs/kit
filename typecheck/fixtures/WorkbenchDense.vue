<script setup lang="ts">
// A dense live console on Workbench: the navigator collapsed by default (unless remembered), no pane headings, the navigator as an overlay that closes on select,
// and the Workbench's keys off. Compiled by bin/typecheck-components.sh; never rendered.
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
</script>
<template>
    <Workbench workbench-key="live-console" label="Console" :default-collapsed="{ navigator: true, inspector: false }" :headings="false" navigator-mode="overlay" close-on-select :keys="false" />
</template>

<script setup lang="ts">
import Workbench from '@/components/Kit/Workbench/Workbench.vue';

// A Workbench with zero items (?workbench, or ?workbench=collapsed with the navigator and inspector collapsed), for the axe run in run.mjs.
const collapsed = new URLSearchParams(window.location.search).get('workbench') === 'collapsed';
</script>
<template>
    <main class="p-4">
        <h1>Empty deck</h1>
        <Workbench workbench-key="empty-deck" label="Deck" :items="[]" :default-collapsed="collapsed ? { navigator: true, inspector: true } : null" />
    </main>
</template>

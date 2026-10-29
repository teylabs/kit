<script setup lang="ts">
import Workbench from '@/components/Kit/Workbench/Workbench.vue';

// A Workbench with zero items, for run.mjs:
// - ?workbench (or ?workbench=collapsed, navigator and inspector collapsed): the axe run, no stage content;
// - ?workbench=stage: a stage-only Workbench with #stage content and no #empty (a presenter console), whose stage must still render;
// - ?workbench=slot: the host's #empty slot, which replaces the stage while there are no items.
const mode = new URLSearchParams(window.location.search).get('workbench');
const collapsed = mode === 'collapsed';
</script>
<template>
    <main class="p-4">
        <h1>Empty deck</h1>
        <Workbench v-if="mode === 'stage'" workbench-key="stage-only" label="Console" :items="[]">
            <template #stage><p data-test-stage>Live slide</p></template>
        </Workbench>
        <Workbench v-else-if="mode === 'slot'" workbench-key="empty-slot" label="Deck" :items="[]">
            <template #stage><p data-test-stage>Live slide</p></template>
            <template #empty="{ label }"><p data-test-empty>Create draft · Import · + New slide ({{ label }})</p></template>
        </Workbench>
        <Workbench v-else workbench-key="empty-deck" label="Deck" :items="[]" :default-collapsed="collapsed ? { navigator: true, inspector: true } : null" />
    </main>
</template>

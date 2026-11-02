<script setup lang="ts">
import { ref } from 'vue';
import SegmentedToggle from '@/components/Kit/Form/SegmentedToggle.vue';
import PaneTabs from '@/components/Kit/Layout/PaneTabs.vue';

// A SegmentedToggle (its third option disabled) and a PaneTabs, for run.mjs: ?segmented in light, ?segmented=dark under .dark. Non-modal, so axe checks contrast.
if (new URLSearchParams(window.location.search).get('segmented') === 'dark') document.documentElement.classList.add('dark');
const view = ref('slides');
const pane = ref('stage');
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <h1>Editor</h1>
        <SegmentedToggle v-model="view" label="Editor view" :options="[{ value: 'slides', label: 'Slides' }, { value: 'outline', label: 'Outline' }, { value: 'notes', label: 'Notes', disabled: true }, { value: 'grid', label: 'Grid' }]" />
        <p data-view>{{ view }}</p>
        <PaneTabs v-model="pane" label="Panes" :tabs="[{ value: 'navigator', label: 'Slides' }, { value: 'stage', label: 'Stage' }]" />
        <button type="button" data-after>After</button>
    </main>
</template>

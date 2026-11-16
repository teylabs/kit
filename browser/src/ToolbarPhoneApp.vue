<script setup lang="ts">
import { ref } from 'vue';
import FloatingToolbar from '@/components/Kit/Layout/FloatingToolbar.vue';

// FloatingToolbar's phone layout, for run.mjs (?toolbar-phone, `&dark`): a bench-like toolbar (two client items, a status, three page actions) that keeps one
// full-width row at phone width, with the rest behind its menu, and the same toolbar unchanged at desktop width.
const items = [
    { key: 'add', label: 'Add comparable', icon: 'plus' },
    { key: 'notes', label: 'Appraiser notes', icon: 'notebook-pen' },
];
const actions = [
    { key: 'preview', label: 'Preview report', url: '/preview', method: 'get' },
    { key: 'finalize', label: 'Finalize', url: '/finalize', method: 'post' },
    { key: 'reopen', label: 'Re-open', url: '/reopen', method: 'post' },
];
const used = ref<string[]>([]);
</script>
<template>
    <main class="min-h-[200vh] bg-background p-4 text-foreground">
        <h1>Bench</h1>
        <p class="h-[150vh]">Long content scrolls under the toolbar.</p>
        <FloatingToolbar
            mode="float"
            data-phone-toolbar
            :items="items"
            :status="{ state: 'saved', label: 'Saved · Yesterday, 1:09 PM' }"
            :actions="actions"
            :on-action="(item) => used.push(item.key)"
            :on-page-action="(action) => used.push(action.key ?? '')"
        />
        <p data-used>{{ used.join(',') }}</p>
    </main>
</template>

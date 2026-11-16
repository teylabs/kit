<script setup lang="ts">
import { provide } from 'vue';
import Page from '@/components/Kit/Layout/Page.vue';

provide('kit.shell', true);
// A long page title beside header actions (?page-title): from sm up the title wraps in its own column and the actions stay on its row; below sm the row may wrap.
const actions = [
    { key: 'edit', label: 'Edit', url: '/cases/1/edit', method: 'get' as const },
    { key: 'report', label: 'Preview report', url: '/cases/1/report', method: 'get' as const },
];
</script>
<template>
    <main>
        <Page title="2006 VINTAGE Trailer 844GNLQ12-D Outlaw Enclosed Camper / Hauler, Goose neck Outlaw" :actions="actions">
            <p>Content</p>
        </Page>
    </main>
</template>

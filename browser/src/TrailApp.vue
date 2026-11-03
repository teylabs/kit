<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, provide } from 'vue';
import Breadcrumbs from '@/components/Kit/Layout/Breadcrumbs.vue';
import Page from '@/components/Kit/Layout/Page.vue';

// The breadcrumb trail in the shell (?trail=page|bar): a tabbed frame (<Page>) round a tab (<Page>), as a record page with tabs lays itself out. `page` (the
// default) draws the trail once, above the outer heading, ancestors only; `bar` draws the whole trail in the shell's bar, the current page included, and no
// <Page> draws a line of its own. The shell's Layout is stood in for by the two values it provides.
const mode = new URLSearchParams(location.search).get('trail') === 'bar' ? 'bar' : 'page';
provide('kit.shell', true);
provide('kit.shell.breadcrumbs', computed(() => mode));
usePage().props.layout = {
    title: 'A-0001',
    breadcrumbs: [
        { label: 'Home', url: '/', home: true, current: false },
        { label: 'Assignments', url: '/assignments', home: false, current: false },
        { label: 'A-0001', url: '/assignments/1', home: false, current: true },
    ],
};
</script>
<template>
    <div v-if="mode === 'bar'" data-test-bar><Breadcrumbs bar /></div>
    <Page>
        <Page title="Valuation"><p>Tab content</p></Page>
    </Page>
</template>

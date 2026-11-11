<script setup lang="ts">
import BulkDrop from '@/components/Kit/Form/BulkDrop.vue';

// BulkDrop in each state for run.mjs: ?bulk-drop in light, ?bulk-drop=dark under .dark. Idle (and, after the test attaches a refused file, its error), busy
// (the uploading look), disabled with and without a prompt, and `showLimit: false` with and without its own hint: the host's "Save first to add files here" on a record not yet saved. axe checks contrast.
if (new URLSearchParams(window.location.search).get('bulk-drop') === 'dark') document.documentElement.classList.add('dark');
const common = { accept: ['image/png'], maxBytes: 1024 * 1024, hasFiles: false };
</script>
<template>
    <main class="grid min-h-screen content-start gap-6 bg-background p-8 text-foreground [&>*]:max-w-2xl">
        <h1 class="text-lg font-medium">Attachments</h1>
        <section data-state="idle"><BulkDrop name="idle" label="Photos" v-bind="common" handle-drops /></section>
        <section data-state="busy"><BulkDrop name="busy" label="Scans" v-bind="common" busy="Uploading… 40%" /></section>
        <section data-state="disabled"><BulkDrop name="disabled" label="Receipts" v-bind="common" disabled prompt="Save first to add files here" /></section>
        <section data-state="no-limit"><BulkDrop name="no-limit" label="Logos" v-bind="common" :show-limit="false" /></section>
        <section data-state="own-hint"><BulkDrop name="own-hint" label="Covers" v-bind="common" :show-limit="false" hint="Square images work best" /></section>
        <section data-state="disabled-browse"><BulkDrop name="disabled-browse" label="Invoices" v-bind="common" disabled /></section>
    </main>
</template>

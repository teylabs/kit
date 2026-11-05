<script setup lang="ts">
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import ActivityEntryRow from '@/components/Kit/Feed/ActivityEntryRow.vue';
import RowActions from '@/components/Kit/Table/RowActions.vue';

// Safety guards, for run.mjs (?guards): a confirmed GET in an ActionBar and in RowActions (asks before it navigates, never a bare link), and activity headlines
// (html renders as markup, text never does).
const exportAction = { key: 'export', label: 'Export', url: '/people/export', method: 'get', destructive: false, confirm: { title: 'Export everyone?', description: 'This can take a while.', label: 'Export' } };
const entry = (id: string, headline: { text?: string; html?: string }) => ({ id, kind: 'activity' as const, headline, occurredAt: null, timestamp: '2 min ago', timestampTitle: null, intent: null, meta: [], count: null, truncated: false, children: [], day: null });
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <section data-bar><ActionBar :actions="[exportAction]" /></section>
        <section data-rows><RowActions :actions="[exportAction]" label="Ada Lovelace" /></section>
        <ul>
            <ActivityEntryRow data-text-entry :entry="entry('t', { text: '<img src=x onerror=&quot;window.kitPwned=1&quot;><b>Ada</b> signed' })" />
            <ActivityEntryRow data-html-entry :entry="entry('h', { html: '<strong>Ada</strong> signed' })" />
        </ul>
    </main>
</template>

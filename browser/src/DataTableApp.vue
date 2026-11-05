<script setup lang="ts">
import DataTable from '@/components/Kit/Table/DataTable.vue';

// A DataTable shaped like mcp's Connections table (?datatable in light, ?datatable=dark under .dark), on a ground that is not the card colour: a header that would wrap,
// a date column declared by its descriptor, and a row whose grant removal is plain and Revoke destructive. Non-modal, so axe checks contrast.
// ?datatable=bulk adds two rows and a bulk Archive that asks for a typed confirmation, so the rows are selectable and the BulkBar sends the ids.
const variant = new URLSearchParams(window.location.search).get('datatable');
if (variant === 'dark') document.documentElement.classList.add('dark');
const bulk = variant === 'bulk';
const remove = { key: 'remove-grant:files', label: 'Remove file access', url: '/c/1/grants/files', method: 'delete', destructive: false, confirm: null };
const revoke = { key: 'revoke', label: 'Revoke', url: '/c/1', method: 'delete', destructive: true, confirm: null };
const table = {
    rows: [{ id: 1, assistant: 'Acme Assistant', connectedBy: 'Northwind Farms admin', connected: '2026-10-30T17:28:52+00:00', actions: [remove, revoke] }, ...(bulk ? [{ id: 2, assistant: 'Beta Bot', connectedBy: 'Ana Lopez', connected: '2026-10-31T09:00:00+00:00', actions: [] }] : [])],
    columns: [
        { key: 'assistant', label: 'Assistant', sortable: false, hideable: false, hiddenByDefault: false, align: null, srOnlyLabel: false, sortBy: null },
        { key: 'connectedBy', label: 'Connected by', sortable: false, hideable: false, hiddenByDefault: false, align: null, srOnlyLabel: false, sortBy: null },
        { key: 'connected', label: 'Connected', sortable: false, hideable: false, hiddenByDefault: false, align: null, srOnlyLabel: false, sortBy: null, date: 'date' },
        { key: 'actions', label: 'Actions', sortable: false, hideable: false, hiddenByDefault: false, align: 'end', srOnlyLabel: true, sortBy: null },
    ],
    sort: null, search: null, filters: [], tabs: [], bulkActions: bulk ? [{ key: 'archive', label: 'Archive', url: '/c/archive', method: 'post', destructive: true, confirm: { title: 'Archive the selected?', description: 'They stop working at once.', label: 'Archive', typed: 'archive' } }] : [],
    pagination: { page: 1, perPage: 1, total: 1, from: 1, to: 1, lastPage: 1 },
    emptyState: null,
};
</script>
<template>
    <main class="min-h-screen bg-muted p-4 text-foreground">
        <h1>AI assistants</h1>
        <div class="w-[34rem]"><DataTable :table="table as never" :toolbar="false" row-label="assistant" /></div>
    </main>
</template>

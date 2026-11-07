<script setup lang="ts">
import DataTable from '@/components/Kit/Table/DataTable.vue';

// A DataTable shaped like mcp's Connections table (?datatable in light, ?datatable=dark under .dark), on a ground that is not the card colour: a header that would wrap,
// a date column declared by its descriptor, and a row whose grant removal is plain and Revoke destructive. Non-modal, so axe checks contrast.
// ?datatable=bulk adds two rows and a bulk Archive that asks for a typed confirmation, so the rows are selectable and the BulkBar sends the ids,
// and a plain Notify owners whose confirm asks in a neutral Dialog.
const query = new URLSearchParams(window.location.search);
const variant = query.get('datatable');
// &rows=split|overflow picks the row actions' layout (DataTable's `rowActions`); without it each row takes RowActions' default.
const rows = query.get('rows') as 'split' | 'overflow' | null;
if (variant === 'dark') document.documentElement.classList.add('dark');
const bulk = variant === 'bulk';
// ?datatable=people: a member row with the descriptors the kit's PeopleTable sends (invented names): Change role and Suspend plain (Suspend asks first),
// Make owner a plain role change with a confirm, Remove destructive. Three plain actions, so Change role stays inline and the rest go behind the menu.
const people = variant === 'people';
// ?datatable=lone: Revoke is the row's only action, so it stays inline. It is still a quiet ghost button; only its Dialog's commit is danger.
const lone = variant === 'lone';
const peopleActions = [
    { key: 'change-role', label: 'Change role', url: '/p/1/role', method: 'put', destructive: false, confirm: null },
    { key: 'suspend', label: 'Suspend', url: '/p/1/suspend', method: 'post', destructive: false, confirm: { title: 'Suspend Ana Lopez?', description: 'They lose access until reinstated.', label: 'Suspend member' } },
    { key: 'transfer-ownership', label: 'Make owner', url: '/p/1/owner', method: 'post', destructive: false, confirm: { title: 'Transfer ownership to Ana Lopez?', description: 'They become the owner.', label: 'Transfer ownership' } },
    { key: 'remove', label: 'Remove', url: '/p/1', method: 'delete', destructive: true, confirm: { title: 'Remove Ana Lopez?', description: 'They lose access straight away.', label: 'Remove member' } },
];
const remove = { key: 'remove-grant:files', label: 'Remove', ariaLabel: 'Remove file access', url: '/c/1/grants/files', method: 'delete', destructive: false, confirm: null };
const revoke = { key: 'revoke', label: 'Revoke', url: '/c/1', method: 'delete', destructive: true, confirm: null };
const loneRevoke = { ...revoke, confirm: { title: 'Revoke Acme Assistant?', description: 'It stops working at once.', label: 'Revoke access' } };
const table = {
    rows: [{ id: 1, assistant: 'Acme Assistant', connectedBy: 'Northwind Farms admin', connected: '2026-10-30T17:28:52+00:00', actions: people ? peopleActions : lone ? [loneRevoke] : [remove, revoke] }, ...(bulk ? [{ id: 2, assistant: 'Beta Bot', connectedBy: 'Ana Lopez', connected: '2026-10-31T09:00:00+00:00', actions: [] }] : [])],
    columns: [
        { key: 'assistant', label: 'Assistant', sortable: false, hideable: false, hiddenByDefault: false, align: null, srOnlyLabel: false, sortBy: null },
        { key: 'connectedBy', label: 'Connected by', sortable: false, hideable: false, hiddenByDefault: false, align: null, srOnlyLabel: false, sortBy: null },
        { key: 'connected', label: 'Connected', sortable: false, hideable: false, hiddenByDefault: false, align: null, srOnlyLabel: false, sortBy: null, date: 'date' },
        { key: 'actions', label: 'Actions', sortable: false, hideable: false, hiddenByDefault: false, align: 'end', srOnlyLabel: true, sortBy: null },
    ],
    sort: null, search: null, filters: [], tabs: [], bulkActions: bulk ? [{ key: 'archive', label: 'Archive', url: '/c/archive', method: 'post', destructive: true, confirm: { title: 'Archive the selected?', description: 'They stop working at once.', label: 'Archive', typed: 'archive' } }, { key: 'notify', label: 'Notify owners', url: '/c/notify', method: 'post', destructive: false, confirm: { title: 'Notify the owners?', description: 'Each owner gets an email.', label: 'Notify' } }] : [],
    pagination: { page: 1, perPage: 1, total: 1, from: 1, to: 1, lastPage: 1 },
    emptyState: null,
};
</script>
<template>
    <main class="min-h-screen bg-muted p-4 text-foreground">
        <h1>AI assistants</h1>
        <div class="w-[34rem]"><DataTable :table="table as never" :toolbar="false" row-label="assistant" :row-actions="rows ? { variant: rows } : null" /></div>
    </main>
</template>

<script setup lang="ts">
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import BulkBar from '@/components/Kit/Table/BulkBar.vue';
import RowActions from '@/components/Kit/Table/RowActions.vue';

// Row action variants and names, for run.mjs (?row-variants), invented names: the default rule (one primary plus menu items is split, two inline actions are
// overflow), each variant chosen explicitly over the default, an `ariaLabel` on RowActions, ActionBar and BulkBar, a label too long for a row (clamped), and a
// confirmation without a title that asks "<Label> this item?" with a long record name in its body, wrapped in full, and grant rows that
// all read "Remove", each described by its grant in the menu.
const record =
    'Northwind Farms quarterly supplier reconciliation for the Eastern region';
const splitRow = [
    {
        key: 'open',
        label: 'Open',
        url: '/r/1',
        method: 'post',
        destructive: false,
        confirm: null,
    },
    {
        key: 'archive',
        label: 'Archive',
        url: '/r/1/archive',
        method: 'post',
        destructive: false,
        disabled: true,
        reason: 'Already archived',
        confirm: null,
    },
    {
        key: 'export',
        label: 'Export',
        url: '/r/1/export',
        method: 'post',
        destructive: false,
        confirm: null,
    },
    {
        key: 'delete',
        label: 'Delete',
        url: '/r/1',
        method: 'delete',
        destructive: true,
        confirm: {
            label: 'Delete',
            description: `Deleting ${record} removes its lines and its history. This cannot be undone.`,
        },
    },
];
const overflowRow = [
    {
        key: 'open',
        label: 'Open',
        url: '/r/2',
        method: 'post',
        destructive: false,
        confirm: null,
    },
    {
        key: 'copy',
        label: 'Copy',
        url: '/r/2/copy',
        method: 'post',
        destructive: false,
        confirm: null,
    },
];
const named = [
    {
        key: 'remove-grant:files',
        label: 'Remove',
        ariaLabel: 'Remove Files',
        url: '/c/1/grants/files',
        method: 'delete',
        destructive: false,
        confirm: null,
    },
    {
        key: 'revoke',
        label: 'Revoke',
        url: '/c/1',
        method: 'delete',
        destructive: true,
        confirm: null,
    },
];
// Several grants that all read "Remove", each described by its grant (one too long for the menu, truncated), with a short server title.
const grant = (scope: string, name: string) => ({
    key: `remove-grant:${scope}`,
    label: 'Remove',
    ariaLabel: `Remove ${name}`,
    description: name,
    url: `/c/2/grants/${scope}`,
    method: 'delete',
    destructive: false,
    confirm: {
        title: 'Remove permission?',
        description: `The '${name}' permission is removed from Acme Assistant. It keeps its connection.`,
        label: 'Remove grant',
    },
});
const grants = [
    {
        key: 'open',
        label: 'Open',
        url: '/c/2',
        method: 'post',
        destructive: false,
        confirm: null,
    },
    grant('files', 'Files'),
    grant('calendar', 'Calendar'),
    grant(
        'ledger',
        'Read and export every ledger entry across all of the workspace accounts',
    ),
];
const long = [
    {
        key: 'resend',
        label: 'Resend the invitation email to Ana Lopez',
        url: '/p/3/resend',
        method: 'post',
        destructive: false,
        confirm: null,
    },
    {
        key: 'details',
        label: 'Show the full billing details',
        url: '/p/3/details',
        method: 'post',
        destructive: false,
        confirm: null,
    },
];
const bar = [
    {
        key: 'publish',
        label: 'Publish',
        ariaLabel: 'Publish Spring menu',
        url: '/posts/3/publish',
        method: 'post',
    },
];
const bulk = [
    {
        key: 'archive',
        label: 'Archive',
        ariaLabel: 'Archive the selected people',
        url: '/people/bulk-archive',
        method: 'post',
    },
];
</script>
<template>
    <main class="bg-background text-foreground min-h-screen p-4">
        <h1 class="sr-only">Row action variants</h1>
        <section data-default-split>
            <RowActions :actions="splitRow" :label="`Actions for ${record}`" />
        </section>
        <section data-default-overflow>
            <RowActions :actions="overflowRow" label="Actions for Ana Lopez" />
        </section>
        <section data-explicit-overflow>
            <RowActions
                :actions="splitRow"
                label="Actions for Ana Lopez"
                variant="overflow"
            />
        </section>
        <section data-explicit-split>
            <RowActions
                :actions="overflowRow"
                label="Actions for Ana Lopez"
                variant="split"
            />
        </section>
        <section data-named>
            <RowActions
                :actions="named"
                label="Actions for Acme Assistant"
                variant="overflow"
            />
        </section>
        <section data-grants>
            <RowActions
                :actions="grants"
                label="Actions for Acme Assistant"
                variant="split"
            />
        </section>
        <section data-long>
            <RowActions
                :actions="long"
                label="Actions for Ana Lopez"
                variant="split"
            />
        </section>
        <section data-bar><ActionBar :actions="bar" /></section>
        <BulkBar :ids="[1, 2]" :actions="bulk" />
    </main>
</template>

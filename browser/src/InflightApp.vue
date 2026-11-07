<script setup lang="ts">
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import BulkBar from '@/components/Kit/Table/BulkBar.vue';
import RowActions from '@/components/Kit/Table/RowActions.vue';

// In-flight guards and disabled reasons, for run.mjs (?inflight): RowActions, an ActionBar that runs its own actions and a BulkBar, each with a POST action a
// test holds in flight (window.kitHold), and a disabled action with a `reason`, inline and in a menu.
const row = [
    { key: 'archive', label: 'Archive', url: '/people/7/archive', method: 'post', destructive: false, confirm: null },
    { key: 'resend', label: 'Resend invite', url: '/people/7/resend', method: 'post', destructive: false, disabled: true, reason: 'Already accepted', confirm: null },
];
const rowMenu = [
    { key: 'archive', label: 'Archive', url: '/people/8/archive', method: 'post', destructive: false, confirm: null },
    { key: 'suspend', label: 'Suspend', url: '/people/8/suspend', method: 'post', destructive: false, confirm: null },
    { key: 'export', label: 'Export', url: '/people/8/export', method: 'post', destructive: false, confirm: null },
    { key: 'view', label: 'View', url: '/people/8', method: 'get', destructive: false, confirm: null },
    { key: 'remove', label: 'Remove', url: '/people/8', method: 'delete', destructive: true, disabled: true, reason: 'Owners cannot be removed', confirm: null },
];
const bar = [
    { key: 'publish', label: 'Publish', url: '/posts/3/publish', method: 'post' },
    { key: 'unpublish', label: 'Unpublish', url: '/posts/3/unpublish', method: 'post', disabled: true, reason: 'Not published yet' },
];
const menuBar = [
    { key: 'share', label: 'Share', url: '/posts/3/share', method: 'post' },
    { key: 'delete', label: 'Delete', url: '/posts/3', method: 'delete', destructive: true, disabled: true, reason: 'Published posts cannot be deleted' },
];
const bulk = [{ key: 'archive', label: 'Archive', url: '/people/bulk-archive', method: 'post' }];
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <h1 class="sr-only">In-flight guards</h1>
        <section data-rows><RowActions :actions="row" label="Actions for Ana Lopez" /></section>
        <section data-menu-rows><RowActions :actions="rowMenu" label="Actions for Ada Lovelace" /></section>
        <section data-bar><ActionBar :actions="bar" /></section>
        <section data-menu-bar><ActionBar :actions="menuBar" /></section>
        <BulkBar :ids="[1, 2]" :actions="bulk" />
    </main>
</template>

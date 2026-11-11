<script setup lang="ts">
import ActionBar from '@/components/Kit/Action/ActionBar.vue';

// ActionBar with `item-class`, for run.mjs (?item-class), invented labels: its More menu's rows take the host's row height, Cancel stays destructive.
const post = (key: string, label: string, extra = {}) => ({ key, label, url: `/orders/12/${key}`, method: 'post', destructive: false, confirm: null, ...extra });
const actions = [
    post('approve', 'Approve order'),
    post('edit', 'Edit details'),
    post('archive', 'Archive order', { disabled: true, reason: 'Already archived' }),
    post('cancel', 'Cancel order', { destructive: true }),
];
</script>
<template>
    <main class="flex min-h-screen flex-col items-start gap-6 bg-background p-4 text-foreground">
        <span data-destructive-probe class="sr-only text-destructive-text" aria-hidden="true"></span>
        <section data-item-class class="w-full"><ActionBar :actions="actions" item-class="min-h-12" /></section>
    </main>
</template>

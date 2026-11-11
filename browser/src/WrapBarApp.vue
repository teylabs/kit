<script setup lang="ts">
import ActionBar from '@/components/Kit/Action/ActionBar.vue';

// ActionBar at phone width, for run.mjs (?wrap-bar, `&dark` for the dark tokens), invented labels: `wrap` keeps every action visible on wrapped rows and renders
// no compact copy; the same actions without `wrap` compact into the main action and a More menu, whose Cancel row is destructive and whose rows without an icon
// line up with the rows that have one.
const post = (key: string, label: string, extra = {}) => ({ key, label, url: `/orders/12/${key}`, method: 'post', destructive: false, confirm: null, ...extra });
const actions = [
    post('approve', 'Approve order'),
    post('edit', 'Edit details'),
    post('assign', 'Assign courier'),
    post('reopen', 'Reopen order'),
    post('archive', 'Archive order'),
    post('cancel', 'Cancel order', { destructive: true }),
];
</script>
<template>
    <main class="flex min-h-screen flex-col items-start gap-6 bg-background p-4 text-foreground">
        <span data-destructive-probe class="sr-only text-destructive-text" aria-hidden="true"></span>
        <section data-wrap class="w-full"><ActionBar :actions="actions" wrap filled /></section>
        <section data-default class="w-full"><ActionBar :actions="actions" filled /></section>
    </main>
</template>

<script setup lang="ts">
import ActionBar from '@/components/Kit/Action/ActionBar.vue';

// ActionBar's leading segment on the ButtonGroup (outline) path, for run.mjs (?filled-bar, `&dark` for the dark tokens), invented labels: `primary` alone
// (semibold, no fill), `filled` (the primary fill), and `filled` with a server-disabled leading action and a disabled second one, which must read as disabled.
const actions = [
    { key: 'approve', label: 'Approve booking', url: '/bookings/7/approve', method: 'post', destructive: false, confirm: null },
    { key: 'note', label: 'Add note', url: '/bookings/7/notes', method: 'post', destructive: false, confirm: null },
];
const disabledActions = [
    { ...actions[0], disabled: true, reason: 'Review the booking first' },
    { ...actions[1], disabled: true, reason: 'Review the booking first' },
];
</script>
<template>
    <main class="flex min-h-screen flex-col items-start gap-6 bg-background p-8 text-foreground">
        <span data-primary-probe class="sr-only bg-primary text-primary-foreground" aria-hidden="true"></span>
        <section data-primary><ActionBar :actions="actions" primary /></section>
        <section data-filled><ActionBar :actions="actions" filled /></section>
        <section data-filled-disabled><ActionBar :actions="disabledActions" filled /></section>
    </main>
</template>

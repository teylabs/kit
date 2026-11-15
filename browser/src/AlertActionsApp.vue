<script setup lang="ts">
import Alert from '@/components/Kit/Feedback/Alert.vue';
import Button from '@/components/Kit/Form/Button.vue';

// An Alert's actions in its tone, for run.mjs (?alert-actions, ?alert-actions=dark under .dark): every tone in both `actionsStyle`s with a primary Button, a
// secondary one and a `variant="link"` one (soft is the default, so only link is passed), the solid tier, a Button with `href` and a ghost one (the default
// style), a plain link in the slot, and a Button in the body (which keeps the host's look). Invented copy only.
if (new URLSearchParams(window.location.search).get('alert-actions') === 'dark') document.documentElement.classList.add('dark');
const tones = [
    { variant: 'info', title: 'Backups run nightly', body: 'Scheduled backups start at 2:00 each night.', primary: 'View schedule', secondary: 'Dismiss', link: 'How backups work' },
    { variant: 'success', title: 'Import finished', body: '1,204 rows were added to the ledger.', primary: 'View rows', secondary: 'Review', link: 'Import log' },
    { variant: 'warning', title: 'Card expires soon', body: 'The card on file expires at the end of the month.', primary: 'Update card', secondary: 'Remind me later', link: 'Billing help' },
    { variant: 'danger', title: 'Sync failed', body: 'The last sync stopped after three errors.', primary: 'Retry sync', secondary: 'View errors', link: 'Status page' },
    { variant: 'muted', title: 'Read-only', body: 'This record is closed. Reopen it to make changes.', primary: 'Reopen', secondary: 'Dismiss', link: 'Why?' },
    { variant: 'primary', title: 'New: saved views', body: 'Save a filter and find it again from the sidebar.', primary: 'Try it', secondary: 'Dismiss', link: 'Learn more' },
    { variant: 'brand', title: 'Invite your team', body: 'Workspaces are better with people in them.', primary: 'Invite people', secondary: 'Not now', link: 'Learn more' },
] as const;
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground sm:p-8">
        <div class="mx-auto grid max-w-3xl gap-4">
            <h1 class="text-lg font-semibold">Alert actions</h1>
            <template v-for="style in ['link', 'soft'] as const" :key="style">
                <Alert
                    v-for="tone in tones"
                    :key="`${style}-${tone.variant}`"
                    :variant="tone.variant"
                    :title="tone.title"
                    :actions-style="style === 'soft' ? undefined : style"
                    :data-test-alert="`${tone.variant}-${style}`"
                >
                    {{ tone.body }}
                    <template #actions>
                        <Button data-test-primary>{{ tone.primary }}</Button>
                        <Button variant="secondary" data-test-secondary>{{ tone.secondary }}</Button>
                        <Button variant="link" data-test-link>{{ tone.link }}</Button>
                    </template>
                </Alert>
            </template>
            <template v-for="style in ['link', 'soft'] as const" :key="`solid-${style}`">
                <Alert
                    v-for="variant in ['info', 'success', 'warning', 'danger', 'primary', 'brand', 'muted'] as const"
                    :key="`solid-${style}-${variant}`"
                    :variant="variant"
                    tier="solid"
                    title="Maintenance tonight"
                    :actions-style="style === 'soft' ? undefined : style"
                    :data-test-alert="`${variant}-solid-${style}`"
                >
                    Sign-in pauses for ten minutes at 23:00.
                    <template #actions>
                        <Button data-test-primary>Details</Button>
                        <Button variant="secondary" data-test-secondary>Dismiss</Button>
                        <Button variant="link" data-test-link>Status page</Button>
                    </template>
                </Alert>
            </template>
            <Alert variant="warning" title="Payment details" actions-placement="inline" data-test-alert="href">
                Add a billing address before the next invoice.
                <template #actions>
                    <Button href="#billing" data-test-primary>Add address</Button>
                    <Button variant="ghost" data-test-ghost>Later</Button>
                    <a href="#help" data-test-plain>Why?</a>
                </template>
            </Alert>
            <Alert variant="info" title="A form in the body" data-test-alert="body">
                <Button variant="secondary" data-test-body-button>Body button</Button>
            </Alert>
        </div>
    </main>
</template>

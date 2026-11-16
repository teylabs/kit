<script setup lang="ts">
import { ref } from 'vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import Button from '@/components/Kit/Form/Button.vue';

// Typed confirmation, for run.mjs (?confirm): a Dialog that asks for "Acme Ltd" before its action visit, and an ActionBar whose action asks the same.
const open = ref(false);
// `#stage`: a wide Dialog (size lg) whose footer offers the other ways out at its start.
const stage = ref(false);
const confirmed = ref<string | null>(null);
const actions = [{ label: 'Delete workspace', url: '/workspaces/1', method: 'delete', destructive: true, confirm: { title: 'Delete workspace?', description: 'This cannot be undone.', label: 'Delete', typed: 'Acme Ltd' } }];
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <button type="button" data-open-confirm @click="open = true">Delete</button>
        <Dialog v-model:open="open" title="Delete Acme Ltd?" description="This cannot be undone." confirm-label="Delete" confirm-text="Acme Ltd" :action="{ url: '/clients/1', method: 'delete' }" @confirm="value => (confirmed = value ?? null)" />
        <button type="button" data-open-stage @click="stage = true">Change stage</button>
        <Dialog v-model:open="stage" size="lg" title="Change stage?" description="Move this case to Valuation." confirm-label="Change stage">
            <template #footer-start><Button variant="ghost" type="button" data-stage-decline>Decline…</Button><Button variant="ghost" type="button" data-stage-junk>Discard as junk…</Button></template>
        </Dialog>
        <p data-confirmed>{{ confirmed }}</p>
        <section data-bar><ActionBar :actions="actions" /></section>
    </main>
</template>

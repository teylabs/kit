<script setup lang="ts">
import { ref } from 'vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';

// Typed confirmation, for run.mjs (?confirm): a Dialog that asks for "Acme Ltd" before its action visit, and an ActionBar whose action asks the same.
const open = ref(false);
const confirmed = ref<string | null>(null);
const actions = [{ label: 'Delete workspace', url: '/workspaces/1', method: 'delete', destructive: true, confirm: { title: 'Delete workspace?', description: 'This cannot be undone.', label: 'Delete', typed: 'Acme Ltd' } }];
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <button type="button" data-open-confirm @click="open = true">Delete</button>
        <Dialog v-model:open="open" title="Delete Acme Ltd?" description="This cannot be undone." confirm-label="Delete" confirm-text="Acme Ltd" :action="{ url: '/clients/1', method: 'delete' }" @confirm="value => (confirmed = value ?? null)" />
        <p data-confirmed>{{ confirmed }}</p>
        <section data-bar><ActionBar :actions="actions" /></section>
    </main>
</template>

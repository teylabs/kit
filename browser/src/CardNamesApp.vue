<script setup lang="ts">
import ActionCard from '@/components/Kit/Card/ActionCard.vue';
import SummaryCard from '@/components/Kit/Card/SummaryCard.vue';
import Button from '@/components/Kit/Form/Button.vue';

// The swapped card names (v0.16), for run.mjs (?card-names, `&dark` for the dark tokens), invented copy: ActionCard as the panel (centred, pinned top-right with
// content below, and two danger panels, one with the quiet red button the docs advise and one with the filled destructive), and a SummaryCard (the header band)
// with an empty default slot.
const long = 'Members keep their own accounts. Their notes, uploads and comments stay in the workspace and are shown under their name, so nothing they wrote is lost when they leave.';
const hidden = false;
</script>
<template>
    <main class="grid min-h-screen content-start gap-6 bg-background p-4 text-foreground sm:p-8">
        <h1 class="text-lg font-medium">Workspace settings</h1>
        <h2 class="text-base font-medium">General</h2>
        <ActionCard data-case="center" title="Weekly summary" :description="long">
            <template #actions><Button variant="secondary">Change schedule</Button></template>
        </ActionCard>
        <ActionCard data-case="top" title="Export members" description="A CSV of everyone in the workspace." align="top">
            <template #actions><Button>Export</Button></template>
            <p class="text-sm">The last export ran on 3 March.</p>
        </ActionCard>
        <ActionCard data-case="danger" tone="danger" title="Delete workspace" :description="long">
            <template #actions><Button variant="secondary" class="text-destructive-text">Delete workspace</Button></template>
        </ActionCard>
        <ActionCard data-case="danger-filled" tone="danger" title="Leave workspace" description="You lose access straight away.">
            <template #actions><Button variant="destructive">Leave workspace</Button></template>
        </ActionCard>
        <ActionCard data-case="bare" title="No actions">
            <template #actions><span v-if="hidden">never</span></template>
            <span v-if="hidden">never</span>
        </ActionCard>
        <SummaryCard data-case="empty-card" title="Empty SummaryCard">
            <template #actions><Button variant="secondary">Edit</Button></template>
            <span v-if="hidden">never</span>
        </SummaryCard>
    </main>
</template>

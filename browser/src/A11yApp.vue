<script setup lang="ts">
import { ref } from 'vue';
import HeaderStatus from '@/components/Kit/Action/HeaderStatus.vue';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';
import Field from '@/components/Kit/Form/Field.vue';
import Input from '@/components/Kit/Form/Input.vue';
import InputCheckbox from '@/components/Kit/Form/InputCheckbox.vue';
import Textarea from '@/components/Kit/Form/Textarea.vue';
import DescriptionList from '@/components/Kit/Layout/DescriptionList.vue';
import Checklist from '@/components/Kit/Feedback/Checklist.vue';
import EmojiTray from '@/components/Kit/Form/EmojiTray.vue';
import Button from '@/components/Kit/Form/Button.vue';
import Collapsible from '@/components/Kit/Layout/Collapsible.vue';

// Rendered accessibility contracts, for run.mjs (?a11y): Field ids on its controls (only the ids it renders), DescriptionList copy, EmptyState, HeaderStatus.
const name = ref('');
const notes = ref('');
const agree = ref(false);
const reactions = ref<string[]>([]);
const steps = ref<string[]>([]);
const checklist = [
    { key: 'a', label: 'Verify email', state: 'done' as const },
    { key: 'b', label: 'Connect bank', state: 'active' as const, action: { label: 'Connect', url: '/bank' } },
    { key: 'c', label: 'Import invoices', state: 'failed' as const, reason: 'The file was empty.', action: { label: 'Retry', name: 'retry' } },
    { key: 'd', label: 'Invite team', state: 'pending' as const },
];
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <h1>Settings</h1>
        <Field id="plain" label="Plain"><Input id="plain" v-model="name" /></Field>
        <Field id="described" label="Described" description="Shown on invoices." error="Too short."><Input id="described" v-model="name" /></Field>
        <Field id="notes" label="Notes" error="Required."><Textarea id="notes" v-model="notes" /></Field>
        <Field id="agree" label="Agree" error="Accept to continue."><InputCheckbox id="agree" v-model="agree" description="Read the terms first." /></Field>
        <section data-details><DescriptionList :items="[{ term: 'Client ID', description: 'abc-123', copy: true }, { term: 'Secret', description: null, copy: true }]" /></section>
        <EmptyState data-empty :level="2" title="No invoices yet" body="Invoices you send show here." />
        <section data-checklist><Checklist :items="checklist" label="Setup" @action="item => steps.push(item.key)" /></section>
        <p data-steps>{{ steps.join(',') }}</p>
        <section data-emoji><EmojiTray :reactions="[{ emoji: '👍', label: 'Like' }, { emoji: '🎉', label: 'Celebrate' }, { emoji: '❤️', label: 'Love' }]" @react="r => reactions.push(r.label)" /></section>
        <p data-reactions>{{ reactions.join(',') }}</p>
        <section data-buttons><Button href="/people">People</Button> <Button href="https://example.com" target="_blank" external>Site</Button> <Button href="/people/7/archive" method="post">Archive</Button></section>
        <section data-collapsible><Collapsible title="Advanced" :level="2"><p data-collapsible-body>Hidden detail</p></Collapsible></section>
        <section data-status><HeaderStatus :status="{ state: 'saved', label: 'Saved' }" /></section>
    </main>
</template>

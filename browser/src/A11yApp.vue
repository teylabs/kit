<script setup lang="ts">
import { ref } from 'vue';
import HeaderStatus from '@/components/Kit/Action/HeaderStatus.vue';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';
import Field from '@/components/Kit/Form/Field.vue';
import Input from '@/components/Kit/Form/Input.vue';
import InputCheckbox from '@/components/Kit/Form/InputCheckbox.vue';
import Textarea from '@/components/Kit/Form/Textarea.vue';
import DescriptionList from '@/components/Kit/Layout/DescriptionList.vue';

// Rendered accessibility contracts, for run.mjs (?a11y): Field ids on its controls (only the ids it renders), DescriptionList copy, EmptyState, HeaderStatus.
const name = ref('');
const notes = ref('');
const agree = ref(false);
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
        <section data-status><HeaderStatus :status="{ state: 'saved', label: 'Saved' }" /></section>
    </main>
</template>

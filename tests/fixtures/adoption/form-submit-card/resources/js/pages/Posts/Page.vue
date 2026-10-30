<script setup lang="ts">
import { Field, FormCard, Input, useSubmitForm } from '@/components/Kit';

const form = useSubmitForm({ title: '' }, async (data) => {
    const response = await fetch('/api/posts', { method: 'POST', headers: { 'content-type': 'application/json', accept: 'application/json' }, body: JSON.stringify(data) });
    if (!response.ok) throw response;
});
</script>
<template>
    <FormCard :form="form" title="New post">
        <Field id="title" label="Title" :error="form.errors.title"><Input id="title" v-model="form.data.title" name="title" /></Field>
    </FormCard>
</template>

<script setup lang="ts">
import { useSubmitForm } from '@/components/Kit';

const form = useSubmitForm({ title: '' }, async (data) => {
    const response = await fetch('/api/posts', { method: 'POST', body: JSON.stringify(data) });
    if (!response.ok) throw response;
});
</script>
<template>
    <form novalidate @submit.prevent="form.submit()">
        <input v-model="form.data.title" type="text" name="title">
        <button type="submit">Save</button>
    </form>
</template>

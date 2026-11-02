<script setup lang="ts">
import Field from '@/components/Kit/Form/Field.vue';
import FormCard from '@/components/Kit/Form/FormCard.vue';
import Input from '@/components/Kit/Form/Input.vue';
import { useSubmitForm } from '@/components/Kit/Form/submitForm';

// A FormCard over a useSubmitForm whose handler is a fake socket (?form-submit): every send waits in window.kitSocket until the test answers it with
// respond('ok'), or a thrown fetch Response of a 422 with Laravel's errors. Nothing here visits; the test checks the URL and history stay put.
type Pending = { resolve: () => void; reject: (error: unknown) => void };
const socket = ((window as unknown as { kitSocket: { sent: unknown[]; pending: Pending[]; respond: (kind: 'ok' | '422') => void } }).kitSocket = {
    sent: [],
    pending: [],
    respond(kind) {
        const next = this.pending.shift();
        if (kind === 'ok') next?.resolve();
        else next?.reject(new Response(JSON.stringify({ message: 'The given data was invalid.', errors: { title: ['That title is taken.'] } }), { status: 422, headers: { 'content-type': 'application/json' } }));
    },
});
const form = useSubmitForm({ title: '', summary: '' }, (data) => new Promise<void>((resolve, reject) => {
    socket.sent.push(data);
    socket.pending.push({ resolve, reject });
}));
</script>
<template>
    <main class="mx-auto grid max-w-2xl gap-4 p-8">
        <h1 class="text-xl font-semibold">New post</h1>
        <FormCard :form="form" title="Post" :sticky="false">
            <Field id="summary" label="Summary" :error="form.errors.summary"><Input id="summary" v-model="form.data.summary" name="summary" :invalid="!!form.errors.summary" /></Field>
            <Field id="title" label="Title" :error="form.errors.title"><Input id="title" v-model="form.data.title" name="title" :invalid="!!form.errors.title" /></Field>
        </FormCard>
    </main>
</template>

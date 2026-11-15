<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import BulkDrop from '@/components/Kit/Form/BulkDrop.vue';
import FormActions from '@/components/Kit/Form/FormActions.vue';
import Input from '@/components/Kit/Form/Input.vue';
import Toaster from '@/components/Kit/Feedback/Toaster.vue';

// A form page: useForm with FormActions (the leave guard), a direct-upload BulkDrop, and a link away. `then` is where the save's onSuccess visits, if anywhere.
// The shell's Toaster, for the reload after a deploy, and a background reload (router.reload(), async) for Inertia's version change.
const props = defineProps<{ title: string; then?: string | null }>();
const form = useForm({ title: props.title });
function save() {
    form.put('/edit', { onSuccess: () => { if (props.then) router.visit(props.then); } });
}
</script>
<template>
    <form data-page="edit" novalidate @submit.prevent="save">
        <h1>Edit</h1>
        <Input id="title" v-model="form.title" name="title" aria-label="Title" />
        <BulkDrop name="files" label="Files" :accept="['text/plain']" :max-bytes="1000000" :has-files="false" :direct="{ request: '/uploads/request', confirm: '/uploads/confirm' }" />
        <Link href="/other" data-to-other>Other</Link>
        <button type="button" data-refresh @click="router.reload()">Refresh</button>
        <FormActions :form="form" :sticky="false" />
    </form>
    <Toaster />
</template>

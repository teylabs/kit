<script setup lang="ts">
import FormActions from '@/components/Kit/Form/FormActions.vue';
import PasswordInput from '@/components/Kit/Form/PasswordInput.vue';
import { useSubmitForm } from '@/components/Kit/Form/submitForm';

// ?secret-field: an API-key settings form (useSubmitForm) with a `secret` PasswordInput loaded with a saved key, a plain PasswordInput, and a secret one
// whose page passes its own autocomplete. run.mjs checks the password-manager opt-outs, that autofill leaving the loaded value doesn't make the form
// unsaved, and that a save clears the unsaved state, with the key field reset on success.
const w = window as unknown as { kitSaved?: unknown[] };
w.kitSaved = [];
const form = useSubmitForm(
    { apiKey: 'sk-live-loaded', note: '' },
    async (data) => {
        w.kitSaved!.push(data);
    },
    { warnOnLeave: false },
);
</script>
<template>
    <main class="grid max-w-xl gap-6 bg-background p-8 text-foreground">
        <h1 class="text-lg font-medium">Integration</h1>
        <form class="grid gap-4" @submit.prevent="form.submit()">
            <label class="grid gap-2 text-sm"
                >API key
                <PasswordInput v-model="form.data.apiKey as string" name="api_key" secret data-secret-key />
            </label>
            <label class="grid gap-2 text-sm"
                >Note
                <input v-model="form.data.note" name="note" class="rounded-md border border-input px-3 py-2" />
            </label>
            <label class="grid gap-2 text-sm"
                >Password
                <PasswordInput name="password" autocomplete="current-password" data-plain />
            </label>
            <label class="grid gap-2 text-sm"
                >Webhook secret
                <PasswordInput name="webhook_secret" secret autocomplete="off" data-override />
            </label>
            <FormActions :form="form" :sticky="false" :warn-on-leave="false" />
        </form>
    </main>
</template>

<script setup lang="ts">
import { reactive } from 'vue';
import Alert from '@/components/Kit/Feedback/Alert.vue';
import FormCard from '@/components/Kit/Form/FormCard.vue';

// A FormCard whose form was just saved (recentlySuccessful), so FormActions shows "Saved" in text-success-text, and the four pale Alerts (foreground text on a
// tint, a *-text icon), for run.mjs: ?form-saved in light, ?form-saved=dark under .dark. Non-modal and not sticky, so axe checks the status text's contrast.
if (new URLSearchParams(window.location.search).get('form-saved') === 'dark') document.documentElement.classList.add('dark');
const form = reactive({ processing: false, isDirty: false, hasErrors: false, recentlySuccessful: true, progress: null, reset() {} });
</script>
<template>
    <main class="grid min-h-screen max-w-2xl gap-6 bg-background p-8 text-foreground">
        <h1 class="text-lg font-medium">Profile</h1>
        <form @submit.prevent>
            <FormCard title="Details" description="Shown on your invoices." :form="form" :sticky="false" :warn-on-leave="false">
                <label class="grid gap-2 text-sm">Name <input name="name" value="Northwind Farms" class="rounded-md border border-input bg-background px-3 py-2" /></label>
            </FormCard>
        </form>
        <Alert variant="info" title="Info">The import runs overnight.</Alert>
        <Alert variant="success" title="Success">The import finished.</Alert>
        <Alert variant="warning" title="Warning">Two rows were skipped.</Alert>
        <Alert variant="danger" title="Danger">The import failed.</Alert>
    </main>
</template>

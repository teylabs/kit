<script setup lang="ts">
import Alert from '@/components/Kit/Feedback/Alert.vue';
import FormFieldset from '@/components/Kit/Form/FormFieldset.vue';
import FormCard from '@/components/Kit/Form/FormCard.vue';
import Input from '@/components/Kit/Form/Input.vue';
import LockedForm from '@/components/Kit/Form/LockedForm.vue';

// Alert's actionsPlacement and LockedForm's hideLockFooter for run.mjs (?alert-layout): an Alert with actions in the default placement and one inline, then a
// locked FormCard and a lone locked FormFieldset, each with its notice and with hideLockFooter. Invented copy only.
</script>
<template>
    <main class="mx-auto max-w-3xl space-y-6 p-6">
        <Alert data-test-below variant="warning" title="Read-only">
            This record is closed.
            <template #actions><button type="button">Reopen</button></template>
        </Alert>
        <Alert data-test-inline variant="warning" title="Read-only" actions-placement="inline">
            This record is closed.
            <template #actions><button type="button">Reopen</button></template>
        </Alert>
        <LockedForm data-test-footer :save="null" notice="Read-only while this record is closed.">
            <FormCard title="Details"><Input id="shown-name" model-value="Sample" /></FormCard>
        </LockedForm>
        <LockedForm data-test-no-footer :save="null" notice="Read-only while this record is closed." hide-lock-footer>
            <FormCard title="Details"><Input id="hidden-name" model-value="Sample" /></FormCard>
        </LockedForm>
        <LockedForm data-test-fieldset-notice :save="null" notice="Read-only while this record is closed.">
            <FormFieldset name="Fieldset"><Input id="fieldset-shown" model-value="Sample" /></FormFieldset>
        </LockedForm>
        <LockedForm data-test-fieldset-no-notice :save="null" notice="Read-only while this record is closed." hide-lock-footer>
            <FormFieldset name="Fieldset"><Input id="fieldset-hidden" model-value="Sample" /></FormFieldset>
        </LockedForm>
    </main>
</template>

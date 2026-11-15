<script setup lang="ts">
import { provide, reactive, ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import FormActions from '@/components/Kit/Form/FormActions.vue';
import Input from '@/components/Kit/Form/Input.vue';
import InputCalendarDate from '@/components/Kit/Form/InputCalendarDate.vue';
import LockedForm from '@/components/Kit/Form/LockedForm.vue';
import Repeater from '@/components/Kit/Form/Repeater.vue';

// Field's error lookup, InputCalendarDate and FormActions' density (?field-paths, &dark). Inertia's <Form> context is stood in for by the Symbol Inertia
// provides ("InertiaFormContext"), carrying Laravel's dot-path errors: Fields named with bracket, mixed and dot paths; a Repeater whose second row has an
// error, read by a Field named with the row's `name()`; InputCalendarDate empty, filled, required, disabled, bound with v-model and locked, in a form with a
// reset button; and FormActions at its default density and compact. Input events reaching the dates form are counted in window.kitDateInputs.
const errors = reactive<Record<string, string>>({
    title: 'Enter a title.',
    'contacts.0.email': 'Enter an email.',
    'contacts.1.name': 'Enter a name.',
    'lines.1.amount': 'Enter an amount of at least 1.',
});
provide(Symbol('InertiaFormContext'), reactive({ errors }));
const lines = [
    { id: 'l1', amount: '4' },
    { id: 'l2', amount: '0' },
    { id: 'l3', amount: '9' },
];
const due = ref<string | null>('2026-12-24');
const form = reactive({ processing: false, isDirty: true, hasErrors: false, recentlySuccessful: false, progress: null, reset() {} });
const w = window as unknown as { kitDateInputs: number };
w.kitDateInputs = 0;
</script>
<template>
    <div class="bg-background text-foreground min-h-screen">
        <main class="grid max-w-2xl gap-8 p-8">
            <h1 class="text-lg font-medium">Field paths</h1>
            <section class="grid gap-4" data-paths>
                <h2 class="font-medium">Contacts</h2>
                <Field id="title" name="title" label="Title" data-field="plain"><Input id="title" name="title" /></Field>
                <Field id="email-0" name="contacts[0][email]" label="First email" data-field="bracket"><Input id="email-0" name="contacts[0][email]" /></Field>
                <Field id="email-0-mixed" name="contacts[0].email" label="First email again" data-field="mixed"><Input id="email-0-mixed" /></Field>
                <Field id="name-1" name="contacts.1.name" label="Second name" data-field="dot"><Input id="name-1" /></Field>
                <Field id="name-0" name="contacts[0][name]" label="First name" description="As on the card." data-field="clean"><Input id="name-0" /></Field>
                <Field id="explicit" name="contacts[1][name]" label="Explicit" error="Passed in." data-field="explicit"><Input id="explicit" /></Field>
            </section>
            <form aria-label="Lines" class="grid gap-4" data-lines @submit.prevent>
                <h2 class="font-medium">Lines</h2>
                <Repeater name="lines" :rows="lines" item-label="Line" add-label="Add line">
                    <template #row="{ row, name, index }">
                        <Field :id="`amount-${row.id}`" :name="name('amount')" label="Amount" :data-line-field="index"><Input :id="`amount-${row.id}`" :name="name('amount')" :model-value="row.amount" /></Field>
                    </template>
                </Repeater>
            </form>
            <form id="dates" aria-label="Dates" class="grid grid-cols-6 gap-4" @submit.prevent @input="w.kitDateInputs++">
                <InputCalendarDate id="starts" label="Starts" :span="3" data-date="empty" />
                <InputCalendarDate id="ends" name="ends_on" label="Ends" default-value="2026-11-01" :span="3" data-date="filled" />
                <InputCalendarDate id="signed" label="Signed" default-value="2026-10-02" required :span="3" data-date="required" />
                <InputCalendarDate id="closed" label="Closed" default-value="2026-10-03" disabled :span="3" data-date="disabled" />
                <InputCalendarDate id="due" v-model="due" label="Due" empty-label="No due date" clear-label="Remove" :span="3" data-date="model" />
                <output class="col-span-3 text-sm" data-due>{{ due === '' ? '(empty)' : due }}</output>
                <button type="reset" class="col-span-6 justify-self-start text-sm underline" data-dates-reset>Reset</button>
            </form>
            <LockedForm :save="null">
                <InputCalendarDate id="locked" label="Locked" default-value="2026-11-02" data-date="locked" />
            </LockedForm>
            <form aria-label="Bars" class="grid gap-6" @submit.prevent>
                <div class="rounded-lg border" data-bar="default">
                    <FormActions :form="form" :sticky="false" :warn-on-leave="false" />
                </div>
                <div class="rounded-lg border p-3" data-bar="compact">
                    <FormActions :form="form" density="compact" :sticky="false" :warn-on-leave="false" :cancel="false" submit-label="Post" />
                </div>
            </form>
        </main>
    </div>
</template>

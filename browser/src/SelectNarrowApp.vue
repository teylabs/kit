<script setup lang="ts">
import { ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import FormCard from '@/components/Kit/Form/FormCard.vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';

// A long chosen option in a narrow grid column (?select-narrow, AppraisalBench #1846): a FormCard at 48rem, a span-4 and a span-2 Field, each with a
// searchable InputSelect; the span-2 one (about 220px) holds "Potential, awaiting instructions". Invented values.
const statuses = [
    { value: 'open', label: 'Open' },
    { value: 'potential', label: 'Potential, awaiting instructions' },
];
const kind = ref('open');
const status = ref('potential');
const form = {
    processing: false,
    isDirty: false,
    hasErrors: false,
    recentlySuccessful: false,
    progress: null,
    reset() {},
};
</script>
<template>
    <div class="max-w-[48rem] p-4">
        <FormCard
            title="Assignment"
            :form="form"
            :sticky="false"
            :warn-on-leave="false"
        >
            <Field id="kind" label="Kind" :span="4"
                ><InputSelect
                    id="kind"
                    v-model="kind"
                    :options="statuses"
                    searchable
            /></Field>
            <Field id="status" label="Status" :span="2" data-narrow
                ><InputSelect
                    id="status"
                    v-model="status"
                    :options="statuses"
                    searchable
            /></Field>
        </FormCard>
    </div>
</template>

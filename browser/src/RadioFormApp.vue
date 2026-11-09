<script setup lang="ts">
import { ref } from 'vue';
import RadioCardGroup, { type RadioCardOption } from '@/components/Kit/Form/RadioCardGroup.vue';

// RadioCardGroup inside a form, for run.mjs (?radio-form): a v-model-only group (no `name`) in each commit mode beside a named one. Submitting writes the
// form's FormData keys and values to [data-submitted], so the tests read exactly what a plain or Inertia <Form> would send.
const options: RadioCardOption[] = [{ value: 'a', label: 'Alpha' }, { value: 'b', label: 'Beta' }, { value: 'c', label: 'Gamma' }];
const view = ref<string | number | null>('a');
const tally = ref<string | number | null>(null);
const plan = ref<string | number | null>('b');
const submitted = ref('');
function submit(event: Event): void {
    submitted.value = JSON.stringify([...new FormData(event.target as HTMLFormElement).entries()]);
}
</script>
<template>
    <main class="grid min-h-screen max-w-3xl gap-6 bg-background p-4 text-foreground">
        <form class="grid gap-6" data-radio-form @submit.prevent="submit">
            <input type="hidden" name="kept" value="yes" />
            <RadioCardGroup id="view" v-model="view" label="View" :options="options" data-group="view" />
            <RadioCardGroup id="tally" v-model="tally" label="Tally" commit="click" :options="options" data-group="tally" />
            <RadioCardGroup id="plan" v-model="plan" name="plan" label="Plan" :options="options" data-group="plan" />
            <button type="submit" data-submit>Save</button>
        </form>
        <p data-view>{{ view }}</p>
        <pre data-submitted>{{ submitted }}</pre>
    </main>
</template>

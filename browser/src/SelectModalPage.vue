<script setup lang="ts">
import { ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';
import Modal from '@/components/Kit/Overlay/Modal.vue';
import type { InputSelectOption } from '@/components/Kit/Form/inputSelect';

// A server-driven modal (select-modal.html) with a `filter="remote"` InputSelect, whose results a host fetches on `search`, a local one beside it and an
// unnamed read-only one. The models show in [data-test-model]; Save posts the first two.
defineProps<{ title: string; submit: { label: string; url: string; method: string } }>();
const rooms = ['North', 'South', 'East', 'West'].map((label) => ({ value: label.toLowerCase(), label }));
const contact = ref<string | null>(null);
const room = ref<string | null>(null);
const results = ref<InputSelectOption[]>([]);
const loading = ref(false);
async function search(query: string) {
    if (!query) return;
    loading.value = true;
    const response = await fetch(`/@acme/contacts?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
    results.value = await response.json();
    loading.value = false;
}
</script>
<template>
    <Modal :title="title" size="sm" :submit="submit">
        <Field id="booking-contact" label="Contact">
            <InputSelect id="booking-contact" v-model="contact" name="contact" filter="remote" :options="results" :loading="loading" :debounce="50" @search="search" />
        </Field>
        <Field id="booking-room" label="Room">
            <InputSelect id="booking-room" v-model="room" name="room" :options="rooms" />
        </Field>
        <Field id="booking-desk" label="Desk">
            <InputSelect id="booking-desk" model-value="north" :options="rooms" readonly />
        </Field>
        <output data-test-model>{{ JSON.stringify({ contact, room }) }}</output>
    </Modal>
</template>

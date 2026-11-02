<script setup lang="ts">
import { ref } from 'vue';
import AddressFieldset from '@/components/Kit/Form/AddressFieldset.vue';
import AddressText from '@/components/Kit/Form/AddressText.vue';
import type { Address } from '@/components/Kit/Form/address';

// An address for run.mjs (?address): the fieldset in en-CA, and the same address read back, for axe, the group name, the country-led labels and the postal hint.
const address = ref<Address>({ country: 'CA' });
</script>
<template>
    <main class="max-w-xl p-4">
        <h1>Client</h1>
        <form class="grid grid-cols-6 gap-4">
            <AddressFieldset v-model="address" name="mailing" title="Mailing address" locale="en-CA" />
        </form>
        <AddressText :address="{ line1: '24 Sussex Dr', city: 'Ottawa', region: 'ON', postal_code: 'K1M 1M4', country: 'CA' }" locale="en-CA" data-test-text />
        <output data-test-model>{{ JSON.stringify(address) }}</output>
    </main>
</template>

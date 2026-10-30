<script setup lang="ts">
// AddressFieldset, RegionSelect and AddressText with each prop. Compiled by bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import AddressFieldset from '@/components/Kit/Form/AddressFieldset.vue';
import AddressText from '@/components/Kit/Form/AddressText.vue';
import RegionSelect from '@/components/Kit/Form/RegionSelect.vue';
import type { Address } from '@/components/Kit/Form/address';

const address = ref<Address>({ line1: '1 Main St', city: 'Ottawa', region: 'ON', postal_code: 'K1A 0B1', country: 'CA' });
const region = ref<string | null>('ON');
const map = (a: Address) => `https://maps.example.test/?q=${encodeURIComponent([a.line1, a.city].join(' '))}`;
</script>
<template>
    <AddressFieldset v-model="address" />
    <AddressFieldset v-model="address" name="billing" title="Billing address" description="Where invoices go." :level="3" :countries="['CA', 'US', 'GB']" locale="fr-CA" required :messages="{ city: 'Ville' }" />
    <AddressFieldset v-model="address" country="CA" disabled />
    <RegionSelect v-model="region" country="US" locale="en-US" id="state" name="state" placeholder="Choose" invalid />
    <RegionSelect v-model="region" :country="null" disabled />
    <AddressText :address="address" />
    <AddressText :address="null" locale="fr-CA" hide-country :map-url="map" :messages="{ map: 'Carte' }" />
</template>

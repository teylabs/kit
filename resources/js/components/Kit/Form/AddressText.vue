<script setup lang="ts">
import TextAddress from '@/components/Kit/Text/TextAddress.vue';
import type { Address, AddressMessages } from './address';

// DEPRECATED: AddressText is now TextAddress (docs/text.md), with the same props and markup. This alias renders a TextAddress for anything that imports it by
// path and is removed in the next minor release; `@kit/ui`'s AddressText is TextAddress itself. Import TextAddress instead.
withDefaults(
    defineProps<{
        address: Address | null | undefined;
        locale?: string;
        hideCountry?: boolean;
        mapUrl?: ((address: Address) => string | null) | null;
        messages?: Partial<AddressMessages>;
    }>(),
    {
        locale: undefined,
        hideCountry: false,
        mapUrl: null,
        messages: () => ({}),
    },
);
</script>
<template>
    <TextAddress
        :address="address"
        :locale="locale"
        :hide-country="hideCountry"
        :map-url="mapUrl"
        :messages="messages"
    />
</template>

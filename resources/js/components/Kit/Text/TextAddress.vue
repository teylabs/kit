<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    addressLines,
    addressMessages,
    type Address,
    type AddressMessages,
} from '@/components/Kit/Form/address';
import { TEXT_SIZES, textLines, type TextSize } from './text';

/*
 * A postal address for reading, in an <address> element, one line per row (docs/text.md, docs/address.md). From `address`, the kit's Address: street, unit,
 * "City, Region Postal" and the country, empty parts dropped, the region named in the locale's language, `hideCountry` dropping the country line. Or from
 * `lines`, lines already written (an array, or a string split on line breaks, else commas); or, with neither, the default slot. `mapUrl` turns on a map link
 * for an `address`: a function from it to a URL of the app's choosing, so the kit picks no map provider and makes no outside request. `size` is sm, base or
 * lg; unset, it takes the surrounding text's. Strings as AddressFieldset's (`address.map`). Renders nothing when there is nothing to show.
 */
const props = withDefaults(
    defineProps<{
        address?: Address | null;
        lines?: readonly string[] | string | null;
        locale?: string;
        hideCountry?: boolean;
        mapUrl?: ((address: Address) => string | null) | null;
        messages?: Partial<AddressMessages>;
        size?: TextSize;
    }>(),
    {
        address: null,
        lines: null,
        locale: undefined,
        hideCountry: false,
        mapUrl: null,
        messages: () => ({}),
        size: undefined,
    },
);
const slots = defineSlots<{ default?: () => unknown }>();
let page: { props: Record<string, unknown> } | null = null;
try {
    page = usePage();
} catch {
    page = null;
}
const locale = computed(
    () =>
        props.locale ??
        (page?.props as { kitNumber?: { locale?: string } } | undefined)
            ?.kitNumber?.locale ??
        (typeof navigator === 'undefined' ? 'en-CA' : navigator.language),
);
const rows = computed(() =>
    props.address
        ? addressLines(props.address, locale.value, !props.hideCountry)
        : textLines(props.lines),
);
const href = computed(() =>
    props.address && props.mapUrl ? props.mapUrl(props.address) : null,
);
const text = computed(() =>
    addressMessages(
        (page?.props as { kitUi?: { address?: unknown } } | undefined)?.kitUi
            ?.address,
        props.messages,
    ),
);
</script>
<template>
    <address
        v-if="rows.length || slots.default"
        :class="[
            'text-pretty break-words not-italic',
            size && TEXT_SIZES[size],
        ]"
        data-slot="text-address"
        data-address-text
    >
        <template v-if="rows.length">
            <template v-for="(line, index) in rows" :key="index"
                >{{ line }}<br v-if="index < rows.length - 1"
            /></template>
        </template>
        <slot v-else />
        <a
            v-if="href"
            :href="href"
            class="text-primary mt-1 block text-sm underline-offset-4 hover:underline"
            data-address-map
            >{{ text.map }}</a
        >
    </address>
</template>

<script setup lang="ts">
import TextNumeric from './TextNumeric.vue';

// DEPRECATED: a decimal amount with a symbol in front and two places ($1,234.50), as TextNumeric with `:decimals="2"` and a `prefix`. It names no currency,
// so it is kept only for apps moving to the kit, for one release. Use Amount (a currency, the locale's symbol placement) for money, or TextNumeric.
withDefaults(
    defineProps<{
        value: number | string | null | undefined;
        symbol?: string | false;
        locale?: string;
    }>(),
    { symbol: '$', locale: undefined },
);
</script>
<template>
    <TextNumeric
        :value="value"
        :decimals="2"
        :locale="locale"
        :prefix="symbol || undefined"
    />
</template>

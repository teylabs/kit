<script setup lang="ts">
import Amount from '@/components/Kit/Form/Amount.vue';
import InputNumber from '@/components/Kit/Form/InputNumber.vue';

// Money and percent for run.mjs (?money): inputs in en-CA and fr-CA, and amounts with accounting negatives, for axe, the accessible names and the decoration.
</script>
<template>
    <main class="grid max-w-md gap-4 p-4">
        <h1>Invoice</h1>
        <form class="grid gap-4">
            <InputNumber name="price" label="Price" kind="money" currency="CAD" locale="en-CA" data-test-price />
            <InputNumber name="prix" label="Prix" kind="money" currency="CAD" locale="fr-CA" data-test-prix />
            <InputNumber name="rate" label="Rate" kind="percent" locale="en-CA" :scale="3" data-test-rate />
            <InputNumber name="balance" label="Balance" kind="money" currency="CAD" locale="en-CA" signed negative="parentheses" data-test-balance />
        </form>
        <p>Total <Amount value="-123456" kind="money" currency="CAD" locale="en-CA" negative="parentheses" data-test-total /></p>
        <p>Tax <Amount value="13.00" kind="percent" locale="fr-CA" data-test-tax /></p>
    </main>
</template>

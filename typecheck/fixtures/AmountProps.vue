<script setup lang="ts">
// Amount with each prop, and InputNumber's percent kind and accounting negatives. Compiled by bin/typecheck-components.sh; never rendered.
import Amount from '@/components/Kit/Form/Amount.vue';
import InputNumber from '@/components/Kit/Form/InputNumber.vue';
</script>
<template>
    <Amount value="123456" kind="money" currency="CAD" />
    <Amount value="-123456" kind="money" currency="CAD" locale="fr-CA" negative="parentheses" class="text-right" />
    <Amount value="12.50" kind="percent" :scale="2" negative="minus" />
    <Amount :value="null" kind="percent" />
    <InputNumber name="rate" label="Rate" kind="percent" :scale="1" />
    <InputNumber name="balance" label="Balance" kind="money" currency="CAD" signed negative="parentheses" />
</template>

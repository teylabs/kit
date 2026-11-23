<script setup lang="ts">
import { ref } from 'vue';
import Input from '@/components/Kit/Form/Input.vue';
import InputDate from '@/components/Kit/Form/InputDate.vue';
import InputDateRange from '@/components/Kit/Form/InputDateRange.vue';
import InputNumber from '@/components/Kit/Form/InputNumber.vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';
import InputTime from '@/components/Kit/Form/InputTime.vue';
import InputTimeRange from '@/components/Kit/Form/InputTimeRange.vue';
import PeriodPicker from '@/components/Kit/Form/PeriodPicker.vue';
import Select from '@/components/Kit/Form/Select.vue';
import Textarea from '@/components/Kit/Form/Textarea.vue';

// Every kit field once (?fields-fill): each reads --kit-field in light and dark, so a theme's fill reaches all of them. Invented values.
const options = [
    { value: 'a', label: 'Alpha' },
    { value: 'b', label: 'Beta' },
];
const text = ref('');
const pick = ref('a');
const many = ref('a');
const note = ref('');
</script>
<template>
    <div class="bg-card grid max-w-xl gap-3 p-4">
        <div data-field="Input">
            <Input v-model="text" aria-label="Input" />
        </div>
        <div data-field="Select">
            <Select v-model="pick" :options="options" aria-label="Select" />
        </div>
        <div data-field="InputSelect">
            <InputSelect
                id="fs-select"
                v-model="many"
                :options="options"
                aria-label="InputSelect"
            />
        </div>
        <div data-field="InputNumber">
            <InputNumber name="amount" label="InputNumber" />
        </div>
        <div data-field="InputDate">
            <InputDate name="day" label="InputDate" />
        </div>
        <div data-field="InputDateRange">
            <InputDateRange name="days" label="InputDateRange" />
        </div>
        <div data-field="InputTime">
            <InputTime name="at" label="InputTime" />
        </div>
        <div data-field="InputTimeRange">
            <InputTimeRange name="hours" label="InputTimeRange" />
        </div>
        <div data-field="PeriodPicker">
            <PeriodPicker mode="month" name="period" label="PeriodPicker" />
        </div>
        <div data-field="Textarea">
            <Textarea v-model="note" aria-label="Textarea" />
        </div>
    </div>
</template>

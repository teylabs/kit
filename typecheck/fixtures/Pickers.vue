<script setup lang="ts">
// The pickers as a host uses them: plain ISO strings in and out, ranges as {start, end}, and the instant helpers through the viewer's zone. Compiled by
// bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import InputDate from '@/components/Kit/Form/InputDate.vue';
import InputCalendarDate from '@/components/Kit/Form/InputCalendarDate.vue';
import InputDateRange from '@/components/Kit/Form/InputDateRange.vue';
import InputMonth from '@/components/Kit/Form/InputMonth.vue';
import InputYear from '@/components/Kit/Form/InputYear.vue';
import InputTime from '@/components/Kit/Form/InputTime.vue';
import InputTimeRange from '@/components/Kit/Form/InputTimeRange.vue';
import { fromInstant, toInstant, type DateRange } from '@/components/Kit/Form/dateInput';

const stay: DateRange = { start: '2026-11-01', end: '2026-11-04' };
const starts = toInstant('2026-11-01', '09:30', 'America/Halifax');
const shown: { date: string; time: string } = fromInstant(starts, 'America/Halifax');
const onDate = (value: string) => value.length;
const onRange = (value: DateRange) => value.start;
const due = ref<string | null>(null);
</script>
<template>
    <InputDate name="due_on" label="Due" default-value="2026-11-01" min="2026-01-01" @canonical="onDate" />
    <InputCalendarDate id="due" v-model="due" label="Due" :span="3" empty-label="No due date" clear-label="Remove" @canonical="onDate" />
    <InputCalendarDate id="paid" name="paid_on" label="Paid" :default-value="null" required />
    <InputDateRange name="stay" label="Stay" :default-value="stay" @canonical="onRange" />
    <InputMonth name="period" label="Month" default-value="2026-10" />
    <InputYear name="founded" label="Founded" max="2026" />
    <InputTime name="opens" label="Opens" default-value="09:30" :step="15" />
    <InputTimeRange name="hours" label="Hours" :default-value="{ start: '09:00', end: '17:00' }" />
    <!-- @vue-expect-error a range's default is {start, end}, not a string -->
    <InputDateRange name="bad" label="Bad" default-value="2026-11-01" />
    <!-- @vue-expect-error `step` is minutes, a number -->
    <InputTime name="bad" label="Bad" step="15" />
    <!-- @vue-expect-error InputCalendarDate needs an id: its label, Field and Clear point at it -->
    <InputCalendarDate label="Bad" />
    <!-- @vue-expect-error `span` is 1 to 6 -->
    <InputCalendarDate id="bad" label="Bad" :span="7" />
    <span>{{ shown.date }}</span>
</template>

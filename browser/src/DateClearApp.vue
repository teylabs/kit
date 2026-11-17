<script setup lang="ts">
import InputCalendarDate from '@/components/Kit/Form/InputCalendarDate.vue';
import InputDate from '@/components/Kit/Form/InputDate.vue';
import InputDateRange from '@/components/Kit/Form/InputDateRange.vue';
import InputMonth from '@/components/Kit/Form/InputMonth.vue';
import InputTime from '@/components/Kit/Form/InputTime.vue';
import InputTimeRange from '@/components/Kit/Form/InputTimeRange.vue';

// InputCalendarDate's inline Clear and the even literal gaps of every segmented date and time input, for run.mjs (?date-clear, &dark under .dark,
// &lang=<locale> on <html lang>, which viewerLocale reads).
const query = new URLSearchParams(window.location.search);
if (query.has('dark')) document.documentElement.classList.add('dark');
document.documentElement.lang = query.get('lang') ?? 'en-US';
</script>
<template>
    <main class="bg-background text-foreground grid max-w-md gap-4 p-4">
        <h1>Date clear</h1>
        <form id="date-clear" class="grid gap-4" @submit.prevent>
            <InputCalendarDate id="loss_date" label="Loss date" default-value="2026-09-05" data-date="loss" />
            <InputCalendarDate id="empty_loss" label="Empty loss date" data-date="empty" />
            <InputDate name="due" label="Due date" default-value="2026-09-05" data-gaps="date" />
            <InputDateRange name="stay" label="Stay" :default-value="{ start: '2026-09-05', end: '2026-11-18' }" data-gaps="range" />
            <InputMonth name="period" label="Billing month" default-value="2026-09" data-gaps="month" />
            <InputTime name="opens" label="Opens at" default-value="09:30" data-gaps="time" />
            <InputTimeRange name="hours" label="Hours" :default-value="{ start: '09:00', end: '17:00' }" data-gaps="time-range" />
        </form>
    </main>
</template>

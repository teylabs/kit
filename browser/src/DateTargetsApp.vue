<script setup lang="ts">
import InputDate from '@/components/Kit/Form/InputDate.vue';
import InputDateRange from '@/components/Kit/Form/InputDateRange.vue';
import InputMonth from '@/components/Kit/Form/InputMonth.vue';
import InputYear from '@/components/Kit/Form/InputYear.vue';
import InputTime from '@/components/Kit/Form/InputTime.vue';
import InputTimeRange from '@/components/Kit/Form/InputTimeRange.vue';
import LockedForm from '@/components/Kit/Form/LockedForm.vue';

// Every kit date and time input for run.mjs (?date-targets, =dark under .dark): read for axe's target-size on each typed segment, filled and empty, and locked.
if (new URLSearchParams(window.location.search).get('date-targets') === 'dark') document.documentElement.classList.add('dark');
</script>
<template>
    <main class="bg-background text-foreground grid max-w-md gap-4 p-4">
        <h1>Date and time targets</h1>
        <form class="grid gap-4" @submit.prevent>
            <InputDate name="due" label="Due date" default-value="2026-11-01" />
            <InputDate name="empty_due" label="Empty date" />
            <InputDateRange name="stay" label="Stay" :default-value="{ start: '2026-11-01', end: '2026-11-08' }" />
            <InputDateRange name="empty_stay" label="Empty stay" />
            <InputMonth name="period" label="Billing month" default-value="2026-10" />
            <InputYear name="founded" label="Founded" default-value="2026" />
            <InputTime name="opens" label="Opens at" default-value="09:30" />
            <InputTime name="empty_opens" label="Empty time" />
            <InputTimeRange name="hours" label="Hours" :default-value="{ start: '09:00', end: '17:00' }" />
        </form>
        <LockedForm :save="null">
            <InputDate name="locked" label="Locked date" default-value="2026-11-01" />
            <InputTime name="locked_time" label="Locked time" default-value="09:30" />
        </LockedForm>
    </main>
</template>

<script setup lang="ts">
import InputDate from '@/components/Kit/Form/InputDate.vue';
import InputDateRange from '@/components/Kit/Form/InputDateRange.vue';
import InputMonth from '@/components/Kit/Form/InputMonth.vue';
import InputYear from '@/components/Kit/Form/InputYear.vue';
import InputTime from '@/components/Kit/Form/InputTime.vue';
import InputTimeRange from '@/components/Kit/Form/InputTimeRange.vue';
import LockedForm from '@/components/Kit/Form/LockedForm.vue';

// The pickers in a page (?pickers): each in a plain form, with its hidden canonical inputs, plus a locked copy of the date picker.
</script>
<template>
    <main class="grid max-w-md gap-4 p-4">
        <h1>Pickers</h1>
        <form id="pickers" class="grid gap-4" @submit.prevent>
            <InputDate name="due" label="Due date" default-value="2026-11-01" />
            <InputDateRange name="stay" label="Stay" />
            <InputMonth name="period" label="Billing month" default-value="2026-10" />
            <InputYear name="founded" label="Founded" default-value="2026" />
            <InputTime name="opens" label="Opens at" default-value="09:30" />
            <InputTimeRange name="hours" label="Hours" :default-value="{ start: '09:00', end: '17:00' }" />
            <button type="submit">Save</button>
        </form>
        <LockedForm :save="null">
            <InputDate name="locked" label="Locked date" default-value="2026-11-01" />
        </LockedForm>
    </main>
</template>

<script setup lang="ts">
// DateTime as a host uses it: a calendar date, an instant in the viewer's zone, a live relative time, and the formatter for a string. Compiled by
// bin/typecheck-components.sh; never rendered.
import DateTime from '@/components/Kit/Data/DateTime.vue';
import { formatDateTime, type DateTimeFormat } from '@/components/Kit/Data/dateTime';

const due = '2026-11-01';
const sent = '2026-10-29T20:30:00-03:00';
const format: DateTimeFormat = 'relative';
const label = formatDateTime(sent, 'ymd', { timezone: 'America/Halifax' });
</script>
<template>
    <DateTime :value="due" format="date" />
    <DateTime :value="sent" :format="format" live :relative-until-days="30" />
    <DateTime :value="sent" format="time" timezone="Pacific/Auckland" locale="en-NZ" />
    <!-- @vue-expect-error `format` is one of the named formats -->
    <DateTime :value="sent" format="fortnightly" />
    <span>{{ label }}</span>
</template>

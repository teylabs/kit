<script setup lang="ts">
import DateTime from '@/components/Kit/Data/DateTime.vue';

// DateTime in a page (?datetime): a calendar date in two far-apart zones, an instant in a fixed zone, and a live relative time.
const now = Date.parse('2026-10-29T12:00:00Z');
</script>
<template>
    <main class="p-4">
        <h1>Dates</h1>
        <p>Due <DateTime value="2026-11-01" format="date" timezone="Pacific/Kiritimati" locale="en-US" data-case="date-east" /> and <DateTime value="2026-11-01" format="date" timezone="Pacific/Pago_Pago" locale="en-US" data-case="date-west" /></p>
        <p>Sent <DateTime value="2026-10-29T23:30:00Z" format="ymd" timezone="Pacific/Kiritimati" data-case="instant-east" /> / <DateTime value="2026-10-29T23:30:00Z" format="ymd" timezone="Pacific/Pago_Pago" data-case="instant-west" /></p>
        <p>Updated <DateTime value="2026-10-29T11:57:00Z" format="relative" :now="now" locale="en-US" data-case="relative" /> · <DateTime :value="new Date(Date.now() - 120000).toISOString()" format="relative" live locale="en-US" data-case="live" /></p>
    </main>
</template>

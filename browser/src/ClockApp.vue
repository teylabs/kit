<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import DateTime from '@/components/Kit/Data/DateTime.vue';
import InputTime from '@/components/Kit/Form/InputTime.vue';
import InputTimeRange from '@/components/Kit/Form/InputTimeRange.vue';

// The viewer's clock (?clock=12h|24h|auto, or ?clock alone for no share at all; &lang= the page's locale): `kitClock` set on the page's props as
// session-ui shares it, read by DateTime and the time inputs. window.kitClock(value) changes the share in place. Two explicit overrides beside them.
const query = new URLSearchParams(window.location.search);
document.documentElement.lang = query.get('lang') || 'en-US';
const page = usePage() as unknown as { props: Record<string, unknown> };
if (query.get('clock')) page.props.kitClock = query.get('clock');
(window as unknown as { kitClock: (value: string) => void }).kitClock = (value) => {
    page.props.kitClock = value;
};
const evening = '2026-10-29T23:30:00Z';
</script>
<template>
    <main class="grid max-w-md gap-4 p-4">
        <h1>Clock</h1>
        <p>At <DateTime :value="evening" format="time" timezone="America/Halifax" data-case="time" />, on <DateTime :value="evening" timezone="America/Halifax" data-case="local" /></p>
        <p>Always 12-hour: <DateTime :value="evening" format="time" timezone="America/Halifax" clock="12h" data-case="explicit" /></p>
        <form id="clock" class="grid gap-4" @submit.prevent>
            <InputTime name="opens" label="Opens at" default-value="15:30" />
            <InputTimeRange name="hours" label="Hours" :default-value="{ start: '09:00', end: '17:00' }" />
            <InputTime name="fixed" label="Always 12-hour" default-value="15:30" :hour-cycle="12" />
        </form>
    </main>
</template>

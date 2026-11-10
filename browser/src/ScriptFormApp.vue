<script setup lang="ts">
import { ref } from 'vue';
import ScriptForm from '@/components/Kit/Form/ScriptForm.vue';

// A ScriptForm whose handler is a stand-in ceremony (?script-form): it stays busy until the test releases it, so a second press during it can be counted.
const runs = ref(0);
// What the last emitted event was: its type and whether ScriptForm prevented the browser's own submit.
const lastEvent = ref('');
const busy = ref(false);
const code = ref('');
let release: () => void = () => {};
(window as unknown as { kitRelease: () => void }).kitRelease = () => release();
async function ceremony(event: SubmitEvent): Promise<void> {
    lastEvent.value = `${event.constructor.name} ${event.type} ${event.defaultPrevented}`;
    runs.value++;
    busy.value = true;
    await new Promise<void>((resolve) => { release = resolve; });
    busy.value = false;
}
</script>
<template>
    <ScriptForm v-slot="{ busy: slotBusy }" class="flex gap-2 p-4" data-test-form :busy="busy" @submit="ceremony">
        <label for="code">Code</label>
        <input id="code" v-model="code" name="code" required />
        <button type="submit" data-test-submit>{{ slotBusy ? 'Waiting…' : 'Go' }}</button>
    </ScriptForm>
    <p data-runs>{{ runs }}</p>
    <p data-event>{{ lastEvent }}</p>
</template>

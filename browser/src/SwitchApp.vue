<script setup lang="ts">
import { ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import InputSwitch from '@/components/Kit/Form/InputSwitch.vue';

// InputSwitch for run.mjs: ?switch in light, ?switch=dark under .dark. In one form: a named switch, a named one with its own value, an unnamed (v-model-only) one,
// one inside a Field with an error, and two disabled ones (off and on). Submitting writes the form's FormData entries to [data-submitted], so the tests read
// exactly what a plain or Inertia <Form> would send.
if (new URLSearchParams(window.location.search).get('switch') === 'dark') document.documentElement.classList.add('dark');
const alerts = ref(false);
const digest = ref(true);
const compact = ref(false);
const share = ref(false);
const frozenOff = ref(false);
const frozenOn = ref(true);
const submitted = ref('');
function submit(event: Event): void {
    submitted.value = JSON.stringify([...new FormData(event.target as HTMLFormElement).entries()]);
}
</script>
<template>
    <main class="grid min-h-screen content-start gap-6 bg-background p-8 text-foreground [&>*]:max-w-2xl">
        <h1 class="text-lg font-medium">Notifications</h1>
        <form class="grid gap-5" data-switch-form @submit.prevent="submit">
            <input type="hidden" name="kept" value="yes" />
            <InputSwitch id="alerts" v-model="alerts" name="alerts" description="Email me when a teammate mentions me." data-probe="alerts">Mention alerts</InputSwitch>
            <InputSwitch id="digest" v-model="digest" name="digest" value="weekly" data-probe="digest">Weekly digest</InputSwitch>
            <InputSwitch id="compact" v-model="compact" data-probe="compact">Compact view</InputSwitch>
            <Field id="share" label="Sharing" error="Turn sharing on to invite guests.">
                <InputSwitch id="share" v-model="share" name="share" required data-probe="share">Share with guests</InputSwitch>
            </Field>
            <InputSwitch id="frozen-off" v-model="frozenOff" name="frozen_off" disabled description="Your plan does not include this." data-probe="frozen-off">Audit export</InputSwitch>
            <InputSwitch id="frozen-on" v-model="frozenOn" name="frozen_on" disabled description="Set by your administrator." data-probe="frozen-on">Two-step sign-in</InputSwitch>
            <button type="submit" class="justify-self-start rounded-md border px-3 py-1.5 text-sm" data-submit>Save</button>
        </form>
        <p data-values>{{ JSON.stringify({ alerts, digest, compact, share, frozenOff, frozenOn }) }}</p>
        <pre data-submitted>{{ submitted }}</pre>
    </main>
</template>

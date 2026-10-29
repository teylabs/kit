<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import FormStatus from '@/components/Kit/Form/FormStatus.vue';

// FormStatus mounted before any visit, as on a forgot-password page. Each submit is an Inertia visit through the stub router (start, the page swap, flash,
// success, finish); the test answers it with window.kitRespond({ props, flash }), so it can look while the visit is in flight.
type Next = { props?: Record<string, unknown>; flash?: Record<string, unknown> };
const form = useForm({ email: '' });
const w = window as unknown as { kitRespond?: (next: Next) => void };
function submit(withForm: boolean) {
    const respond = new Promise<Next>((resolve) => (w.kitRespond = resolve));
    const options = withForm ? { onStart: () => (form.processing = true), onFinish: () => (form.processing = false) } : {};
    (router as unknown as { visit: (url: string, options: object, respond: Promise<Next>) => Promise<void> }).visit('/forgot-password', options, respond);
}
// A useForm() submitted without a router visit (Inertia v3's http path sets form.processing with no start or finish): the response lands, then processing ends.
const page = usePage();
async function submitHttp() {
    const respond = new Promise<Next>((resolve) => (w.kitRespond = resolve));
    form.processing = true;
    const next = await respond;
    page.props = (next.props ?? {}) as typeof page.props;
    form.processing = false;
}
</script>
<template>
    <main class="grid gap-4 p-8">
        <form class="grid gap-2" @submit.prevent="submit(true)">
            <FormStatus :form="form" data-fs-form />
            <button type="submit" data-submit-form>Email me a reset link</button>
            <button type="button" data-submit-http @click="submitHttp">Send without a visit</button>
        </form>
        <form class="grid gap-2" @submit.prevent="submit(false)">
            <FormStatus data-fs-plain />
            <FormStatus flash-key="notice" data-fs-flash />
            <button type="submit" data-submit-plain>Send</button>
        </form>
    </main>
</template>

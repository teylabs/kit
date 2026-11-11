<script setup lang="ts">
import { ref } from 'vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import Surface from '@/components/Kit/Surface/Surface.vue';

// ActionBar on a pill surface, for run.mjs (?fill-all, `&dark` for the dark tokens), invented labels: `fill-all` (one primary pill), `fill-all` with disabled
// actions (still dimmed), the default pill (padding unchanged), and a GET action with intent `panel`, which is a button that emits, never a link.
const post = (key: string, label: string, extra = {}) => ({ key, label, url: `/trips/3/${key}`, method: 'post', destructive: false, confirm: null, ...extra });
const actions = [post('start', 'Start trip'), post('pause', 'Pause'), post('note', 'Add note')];
const disabled = actions.map((action) => ({ ...action, disabled: true, reason: 'The trip has ended' }));
const panel = [{ key: 'details', label: 'Trip details', url: '/trips/3/details', method: 'get', intent: 'panel', destructive: false, confirm: null }];
const emitted = ref<string[]>([]);
</script>
<template>
    <main class="flex min-h-screen flex-col items-start gap-6 bg-background p-8 text-foreground">
        <span data-primary-probe class="sr-only bg-primary text-primary-foreground" aria-hidden="true"></span>
        <Surface shape="pill" padding="sm" data-fill-all><ActionBar :actions="actions" fill-all /></Surface>
        <Surface shape="pill" padding="sm" data-fill-all-disabled><ActionBar :actions="disabled" fill-all /></Surface>
        <Surface shape="pill" padding="sm" data-default-pill><ActionBar :actions="actions" /></Surface>
        <section data-panel><ActionBar :actions="panel" @action="(action) => emitted.push(action.key)" /></section>
        <output data-emitted>{{ emitted.join(',') }}</output>
    </main>
</template>

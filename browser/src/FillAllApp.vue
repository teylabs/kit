<script setup lang="ts">
import { ref } from 'vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import Surface from '@/components/Kit/Surface/Surface.vue';

// ActionBar on a pill surface, for run.mjs (?fill-all, `&dark` for the dark tokens), invented labels: `fill-all` (one primary pill), `fill-all` with `divider-class`, `fill-all` with `trigger-class` and `divider-class="border-r-0"` (also held to 170px, so it compacts with its trigger in view), `fill-all` with disabled
// actions (still dimmed), the default pill (padding unchanged), a menu-only bar with its trigger text unset, `Trip` and null, a menu-only bar and a compacted one with `trigger-class="-ml-px rounded-l-none"`, and a GET action with intent `panel`, which is a button that emits, never a link.
const post = (key: string, label: string, extra = {}) => ({ key, label, url: `/trips/3/${key}`, method: 'post', destructive: false, confirm: null, ...extra });
const actions = [post('start', 'Start trip'), post('pause', 'Pause'), post('note', 'Add note')];
// Two parts (a declared group and a standalone action), so a narrow bar compacts: one part alone shrinks its segments instead.
const grouped = [post('start', 'Start trip', { group: 'trip' }), post('pause', 'Pause', { group: 'trip', menu: true }), post('note', 'Add note')];
// Menu-only (every action behind the ⌄, no inline segment), off the pill: the trigger's text unset, given, and null.
const menuOnly = actions.map((action) => ({ ...action, group: 'more', menu: true }));
const disabled = actions.map((action) => ({ ...action, disabled: true, reason: 'The trip has ended' }));
const panel = [{ key: 'details', label: 'Trip details', url: '/trips/3/details', method: 'get', intent: 'panel', destructive: false, confirm: null }];
const emitted = ref<string[]>([]);
</script>
<template>
    <main class="flex min-h-screen flex-col items-start gap-6 bg-background p-8 text-foreground">
        <span data-primary-probe class="sr-only bg-primary text-primary-foreground" aria-hidden="true"></span>
        <Surface shape="pill" padding="sm" data-fill-all><ActionBar :actions="actions" fill-all /></Surface>
        <Surface shape="pill" padding="sm" data-fill-all-divider><ActionBar :actions="actions" fill-all divider-class="border-sky-300" /></Surface>
        <Surface shape="pill" padding="sm" data-fill-all-trigger><ActionBar :actions="actions" fill-all divider-class="border-r-0" trigger-class="pl-3.5 pr-2 [&>svg]:size-5 ring-1 ring-inset ring-sky-300" /></Surface>
        <Surface shape="pill" padding="sm" data-fill-all-trigger-compact><ActionBar style="width: 170px" :actions="grouped" fill-all divider-class="border-r-0" trigger-class="pl-3.5 pr-2 [&>svg]:size-5 ring-1 ring-inset ring-sky-300" /></Surface>
        <Surface shape="pill" padding="sm" data-fill-all-disabled><ActionBar :actions="disabled" fill-all /></Surface>
        <Surface shape="pill" padding="sm" data-default-pill><ActionBar :actions="actions" /></Surface>
        <section data-menu-label-unset><ActionBar :actions="menuOnly" /></section>
        <section data-menu-label-text><ActionBar :actions="menuOnly" menu-trigger-label="Trip" /></section>
        <section data-menu-label-null><ActionBar :actions="menuOnly" :menu-trigger-label="null" /></section>
        <section data-menu-trigger-class><ActionBar :actions="menuOnly" trigger-class="-ml-px rounded-l-none" /></section>
        <section data-menu-trigger-class-compact><ActionBar style="width: 120px" :actions="grouped" trigger-class="-ml-px rounded-l-none" /></section>
        <section data-panel><ActionBar :actions="panel" @action="(action) => emitted.push(action.key)" /></section>
        <output data-emitted>{{ emitted.join(',') }}</output>
    </main>
</template>

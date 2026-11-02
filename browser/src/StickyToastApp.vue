<script setup lang="ts">
import { toast } from 'vue-sonner';
import { usePage } from '@inertiajs/vue3';
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import Toaster from '@/components/Kit/Feedback/Toaster.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { WorkbenchItem } from '@/components/Kit/Workbench/workbench';

// A filling Workbench (?sticky-toast) whose actions bar sits at the viewport's foot by layout, not by scroll, with the shell's Toaster. `position` sets the
// shell's toast position (kitToasts.position, `auto` by default); `header` adds that many pixels of shell above the Workbench, so the bar's foot sits above the
// viewport's (a taller shell header than the Workbench's 8rem allowance). window.kitToast(message) shows a toast; emitted actions land in window.kitEvents.
const query = new URLSearchParams(window.location.search);
const page = usePage<{ kitToasts?: { position?: string } }>();
page.props.kitToasts = { position: query.get('position') ?? 'auto' };
const header = Number(query.get('header') ?? 0);
const events = ((window as unknown as { kitEvents: string[] }).kitEvents = []);
(window as unknown as { kitToast: (message: string) => void }).kitToast = (message) => { toast.success(message); };
const act = (key: string, label: string, extra: Partial<ActionDescriptor> = {}): ActionDescriptor => ({ key, label, url: `#${key}`, method: 'post', ...extra });
const actions: ActionDescriptor[] = [act('duplicate', 'Duplicate'), act('present', 'Present', { priority: 'secondary' }), act('save', 'Save slide', { priority: 'primary' })];
const items: WorkbenchItem[] = ['Slide 1', 'Slide 2'].map((label, index) => ({ id: `s${index + 1}`, label }));
const onAction = (action: ActionDescriptor) => events.push(action.key);
</script>
<template>
    <main class="p-4">
        <h1>Deck</h1>
        <div v-if="header" :style="{ height: `${header}px` }" data-tall-header />
        <Workbench workbench-key="sticky-deck" label="Deck" :items="items" current="s1" :actions="actions" @action="onAction" />
        <Toaster />
    </main>
</template>

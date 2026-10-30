<script setup lang="ts">
import { toast } from 'vue-sonner';
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import Toaster from '@/components/Kit/Feedback/Toaster.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { WorkbenchItem } from '@/components/Kit/Workbench/workbench';

// A filling Workbench (?sticky-toast) whose actions bar sits at the viewport's foot by layout, not by scroll, with the shell's Toaster (bottom-right by
// default). window.kitToast(message) shows a toast; emitted actions land in window.kitEvents.
const events = ((window as unknown as { kitEvents: string[] }).kitEvents = []);
(window as unknown as { kitToast: (message: string) => void }).kitToast = (message) => { toast.success(message); };
const actions: ActionDescriptor[] = [{ key: 'duplicate', label: 'Duplicate', url: '#duplicate', method: 'post' }, { key: 'save', label: 'Save slide', url: '#save', method: 'post', priority: 'primary' }];
const items: WorkbenchItem[] = ['Slide 1', 'Slide 2'].map((label, index) => ({ id: `s${index + 1}`, label }));
const onAction = (action: ActionDescriptor) => events.push(action.key);
</script>
<template>
    <main class="p-4">
        <h1>Deck</h1>
        <Workbench workbench-key="sticky-deck" label="Deck" :items="items" current="s1" :actions="actions" @action="onAction" />
        <Toaster />
    </main>
</template>

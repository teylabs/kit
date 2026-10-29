<script setup lang="ts">
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { WorkbenchItem } from '@/components/Kit/Workbench/workbench';

// A Workbench of three items with row menus (?workbench-menu), the host's descriptors per item: Move up is off on the first, Move down on the last. The third
// item's menu is withheld with ?workbench-menu=partial, so an item without actions can be checked. Emitted actions land in window.kitEvents.
const partial = new URLSearchParams(window.location.search).get('workbench-menu') === 'partial';
const events = ((window as unknown as { kitEvents: string[] }).kitEvents = []);
const act = (key: string, label: string, extra: Partial<ActionDescriptor> = {}): ActionDescriptor => ({ key, label, url: `/slides/${key}`, method: 'post', ...extra });
const actions = (index: number, count: number): ActionDescriptor[] => [
    act('move-up', 'Move up', { disabled: index === 0 }),
    act('move-down', 'Move down', { disabled: index === count - 1 }),
    act('duplicate', 'Duplicate'),
    act('delete', 'Delete', { destructive: true }),
];
const items: WorkbenchItem[] = ['Slide 1', 'Slide 2', 'Slide 3'].map((label, index) => ({ id: `s${index + 1}`, label, actions: partial && index === 2 ? [] : actions(index, 3) }));
const onAction = (action: ActionDescriptor, item?: WorkbenchItem) => events.push(`${action.key} ${item?.id ?? '-'}`);
</script>
<template>
    <main class="p-4">
        <h1>Deck</h1>
        <Workbench workbench-key="menu-deck" label="Deck" :items="items" current="s1" @action="onAction" />
    </main>
</template>

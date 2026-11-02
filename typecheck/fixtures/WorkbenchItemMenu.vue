<script setup lang="ts">
// Navigator item menus: each item carries its own kit action descriptors (Move up / Move down / Duplicate / Delete, as the host decides per item), and the
// Workbench's `action` event carries the item a descriptor came from. Compiled by bin/typecheck-components.sh; never rendered.
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { WorkbenchItem } from '@/components/Kit/Workbench/workbench';

const move = (key: 'move-up' | 'move-down', label: string, disabled: boolean): ActionDescriptor => ({ key, label, url: `/slides/1/${key}`, method: 'post', disabled });
const items: WorkbenchItem[] = [
    { id: '1', label: 'Slide 1', actions: [move('move-up', 'Move up', true), move('move-down', 'Move down', false), { key: 'duplicate', label: 'Duplicate', url: '/slides/1/duplicate', method: 'post' }, { key: 'delete', label: 'Delete', url: '/slides/1', method: 'delete', destructive: true, confirm: { title: 'Delete slide?', description: 'This removes the slide.', label: 'Delete' } }] },
    { id: '2', label: 'Slide 2' },
];
function run(action: ActionDescriptor, item?: WorkbenchItem): void {
    const id: string | undefined = item?.id;
    void [action.key, id];
}
</script>
<template>
    <Workbench workbench-key="deck" label="Deck" :items="items" @action="run" />
</template>

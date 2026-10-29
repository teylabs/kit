<script setup lang="ts">
// FloatingToolbar's client items with groups, iconOnly and the editor icons, as a slide editor's stage toolbar uses them. Compiled by bin/typecheck-components.sh;
// never rendered.
import FloatingToolbar from '@/components/Kit/Layout/FloatingToolbar.vue';
import type { ToolbarAction } from '@/components/Kit/Layout/toolbarAction';

const items: ToolbarAction[] = [
    { key: 'align-left', label: 'Align left', icon: 'align-left', group: 'text', iconOnly: true },
    { key: 'align-center', label: 'Align centre', icon: 'align-center', group: 'text', iconOnly: true },
    { key: 'earlier', label: 'Move earlier', icon: 'arrow-up', group: 'block' },
    { key: 'later', label: 'Move later', icon: 'arrow-down', group: 'block' },
    { key: 'duplicate', label: 'Duplicate', icon: 'copy', group: 'block' },
    { key: 'hide', label: 'Hide slide', icon: 'eye-off', group: 'slide', disabled: true, reason: 'The only visible slide' },
    { key: 'delete', label: 'Delete', icon: 'trash', group: 'slide' },
];
const run = (action: ToolbarAction) => action.key;
</script>
<template>
    <FloatingToolbar mode="stage" label="Selection" :items="items" @action="run" />
    <FloatingToolbar mode="float" :items="items" :on-action="run" />
</template>

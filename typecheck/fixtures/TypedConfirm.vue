<script setup lang="ts">
// A typed confirmation: Dialog's confirmText with its emitted confirmation, and a descriptor whose confirm asks for typed text. Compiled by bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';

const open = ref(false);
const typed = ref<string | undefined>();
const replace: ActionDescriptor = { key: 'replace', label: 'Replace from source', url: '/sources/1/replace', method: 'post', destructive: true, menu: true, confirm: { title: 'Replace from source?', description: 'This overwrites every record.', label: 'Replace', typed: 'Acme Ltd' } };
</script>
<template>
    <Dialog v-model:open="open" variant="danger" title="Delete Acme Ltd?" confirm-label="Delete" confirm-text="Acme Ltd" :case-sensitive="false" @confirm="confirmation => (typed = confirmation)" />
    <ActionBar :actions="[replace]" />
</template>

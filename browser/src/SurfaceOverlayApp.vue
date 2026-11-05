<script setup lang="ts">
import { ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import FloatingToolbar from '@/components/Kit/Layout/FloatingToolbar.vue';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import Popover from '@/components/Kit/Overlay/Popover.vue';

// Overlays opened from a FloatingToolbar (?surface-overlay): the toolbar provides the inverse tone and pill shape, so its own buttons blend in, but a
// Dialog or Popover opened from it must render ordinary buttons. The triggers stay pill.
const open = ref(false);
</script>
<template>
    <main class="p-4">
        <h1>Bench</h1>
        <div style="height: 1200px"></div>
        <FloatingToolbar mode="float" label="Bench tools">
            <Button data-test-toolbar-button>Add comparable</Button>
            <Dialog v-model:open="open" title="Mark unpaid?" description="The invoice goes back to the unpaid list." confirm-label="Mark unpaid"
                ><Button data-test-dialog-trigger>Mark unpaid</Button></Dialog
            >
            <Popover title="Filters">
                <template #trigger><Button data-test-popover-trigger>Filters</Button></template>
                <Button data-test-popover-button>Apply</Button>
            </Popover>
        </FloatingToolbar>
    </main>
</template>

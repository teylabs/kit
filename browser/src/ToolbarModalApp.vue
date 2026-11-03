<script setup lang="ts">
import { ref } from 'vue';
import FloatingToolbar from '@/components/Kit/Layout/FloatingToolbar.vue';

// A floating toolbar under an open modal (?toolbar-modal). The modal layer stands in for @inertiaui/modal's .im-modal-container: fixed, z-40, mounted at
// the app root and so earlier in the document than the toolbar. The modal is opened from a button in the toolbar's slot, as a host's "Edit" opens one.
const open = ref(false);
</script>
<template>
    <div v-if="open" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50" data-test-modal-layer>
            <div role="dialog" aria-label="Edit" class="w-80 rounded-lg bg-white p-6" data-test-modal>
                <p>Editing</p>
                <button type="button" data-test-modal-close @click="open = false">Close</button>
            </div>
        </div>
    <main class="p-4">
        <h1>Records</h1>
        <div style="height: 1600px"></div>
        <FloatingToolbar mode="float" label="Record tools">
            <button type="button" data-test-open-modal @click="open = true">Edit</button>
        </FloatingToolbar>
    </main>
</template>

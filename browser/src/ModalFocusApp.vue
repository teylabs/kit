<script setup lang="ts">
import { ref } from 'vue';
import ModalPanel from '@/components/Kit/Overlay/ModalPanel.vue';

// ?modal-focus=<ms>[&nofield]: a stand-in for @inertiaui/modal's non-native ModalContent. The panel mounts at once; the enter animation lasts <ms>; then the
// wrapper is marked data-inertiaui-modal-entered="true" and, one frame later, its focus trap focuses the wrapper's first focusable, as createFocusTrap does.
const query = new URLSearchParams(window.location.search);
const duration = Number(query.get('modal-focus') || 300);
const withField = !query.has('nofield');
const open = ref(false);
const entered = ref(false);
const wrapper = ref<HTMLElement>();
function show() {
    open.value = true;
    setTimeout(() => {
        entered.value = true;
        requestAnimationFrame(() => {
            wrapper.value?.querySelector<HTMLElement>('button, input, select, textarea, [tabindex]:not([tabindex="-1"])')?.focus();
            (window as unknown as { trapFocused: boolean }).trapFocused = true;
        });
    }, duration);
}
</script>
<template>
    <main class="p-8">
        <button type="button" data-open @click="show">Open</button>
    </main>
    <div v-if="open" class="fixed inset-0 flex items-center justify-center p-4">
        <div ref="wrapper" role="dialog" aria-modal="true" :data-inertiaui-modal-entered="entered" class="w-full max-w-lg rounded-xl border bg-background">
            <ModalPanel
                title="Rename"
                :form-mode="false"
                busy-label="Saving…"
                cancel-label="Cancel"
                :show-close-button="true"
                initial-focus="field"
                confirm-message="You have unsaved changes."
                :dirty="false"
                :full-screen="false"
                :checks="new Set()"
                @close="open = false"
            >
                <template v-if="withField">
                    <input aria-label="Name" data-first-field class="h-9 rounded-md border px-3" />
                    <input aria-label="Note" data-second-field class="h-9 rounded-md border px-3" />
                </template>
                <p v-else>Nothing to fill in.</p>
            </ModalPanel>
        </div>
    </div>
</template>

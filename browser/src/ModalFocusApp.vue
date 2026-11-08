<script setup lang="ts">
import { computed, ref } from 'vue';
import ModalPanel from '@/components/Kit/Overlay/ModalPanel.vue';

// ?modal-focus=<ms>[&nofield]: a stand-in for @inertiaui/modal's non-native ModalContent. The panel mounts at once; the enter animation lasts <ms>; then the
// wrapper is marked data-inertiaui-modal-entered="true" and, one frame later, its focus trap focuses the wrapper's first focusable, as createFocusTrap does.
const query = new URLSearchParams(window.location.search);
const duration = Number(query.get('modal-focus') || 300);
const withField = !query.has('nofield');
// &entered: the marker is already true when the panel mounts (a zero-length or reduced-motion enter); &notrap: the trap takes no focus, so nothing ends
// the panel's opening window but the marker.
const preEntered = query.has('entered');
const open = ref(false);
const entered = ref(preEntered);
const wrapper = ref<HTMLElement>();
// &nomarker: the wrapper carries no data-inertiaui-modal-entered, so the panel has no marker to watch.
const marker = computed(() => (query.has('nomarker') ? undefined : entered.value));
// Counts the document's live capture listeners for focusin, keydown and pointerdown, so a test can see the panel leaves none behind.
const live = new Set<unknown>();
const add = document.addEventListener.bind(document);
const remove = document.removeEventListener.bind(document);
const key = (type: string, fn: unknown, options: unknown) => (['focusin', 'keydown', 'pointerdown'].includes(type) && (options === true || (options as { capture?: boolean })?.capture) ? `${type}` : null);
document.addEventListener = ((type: string, fn: EventListenerOrEventListenerObject, options?: boolean | AddEventListenerOptions) => {
    if (key(type, fn, options)) live.add(fn);
    add(type, fn, options);
}) as typeof document.addEventListener;
document.removeEventListener = ((type: string, fn: EventListenerOrEventListenerObject, options?: boolean | EventListenerOptions) => {
    if (key(type, fn, options)) live.delete(fn);
    remove(type, fn, options);
}) as typeof document.removeEventListener;
(window as unknown as { openingListeners: () => number }).openingListeners = () => live.size;
function show() {
    open.value = true;
    setTimeout(() => {
        entered.value = true;
        requestAnimationFrame(() => {
            if (!query.has('notrap')) wrapper.value?.querySelector<HTMLElement>('button, input, select, textarea, [tabindex]:not([tabindex="-1"])')?.focus();
            (window as unknown as { trapFocused: boolean }).trapFocused = true;
        });
    }, duration);
}
</script>
<template>
    <main class="p-8">
        <button type="button" data-open @click="show">Open</button>
        <input aria-label="Search the page" data-page-input class="ml-4 h-9 rounded-md border px-3" />
    </main>
    <div v-if="open" class="pointer-events-none fixed inset-0 flex items-center justify-center p-4">
        <div ref="wrapper" role="dialog" aria-modal="true" :data-inertiaui-modal-entered="marker" class="pointer-events-auto w-full max-w-lg rounded-xl border bg-background">
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

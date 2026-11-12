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
// &late=<ms>: the field mounts <ms> after the panel (an async component or a slot filled later). &noclose: no Close button, so with &nofield nothing is tabbable.
// &leave: closing keeps the panel on the page for 500ms, as the package's leave animation does; &closeontrap: Close is clicked in the trap's own frame.
const late = Number(query.get('late') || 0);
const lateReady = ref(!late);
const open = ref(false);
const leaving = ref(false);
const entered = ref(preEntered);
const wrapper = ref<HTMLElement>();
// &nomarker: the wrapper carries no data-inertiaui-modal-entered, so the panel has no marker to watch.
const marker = computed(() => (query.has('nomarker') ? undefined : entered.value));
// Counts the document's live capture listeners for focusin, keydown and pointerdown, so a test can see the panel leaves none behind.
// Tracked per (type, listener) pair: one listener registered for two types counts twice.
const live = new Map<unknown, Set<string>>();
const add = document.addEventListener.bind(document);
const remove = document.removeEventListener.bind(document);
const key = (type: string, fn: unknown, options: unknown) => (['focusin', 'keydown', 'pointerdown'].includes(type) && (options === true || (options as { capture?: boolean })?.capture) ? `${type}` : null);
document.addEventListener = ((type: string, fn: EventListenerOrEventListenerObject, options?: boolean | AddEventListenerOptions) => {
    if (key(type, fn, options)) live.set(fn, (live.get(fn) ?? new Set()).add(type));
    add(type, fn, options);
}) as typeof document.addEventListener;
document.removeEventListener = ((type: string, fn: EventListenerOrEventListenerObject, options?: boolean | EventListenerOptions) => {
    if (key(type, fn, options)) live.get(fn)?.delete(type);
    remove(type, fn, options);
}) as typeof document.removeEventListener;
(window as unknown as { openingListeners: () => number }).openingListeners = () => [...live.values()].reduce((sum, types) => sum + types.size, 0);
function close() {
    if (!query.has('leave')) return (open.value = false);
    leaving.value = true;
    setTimeout(() => {
        open.value = false;
        leaving.value = false;
    }, 500);
}
function show() {
    open.value = true;
    if (late) setTimeout(() => (lateReady.value = true), late);
    setTimeout(() => {
        entered.value = true;
        requestAnimationFrame(() => {
            if (!query.has('notrap')) wrapper.value?.querySelector<HTMLElement>('button, input, select, textarea, [tabindex]:not([tabindex="-1"])')?.focus();
            if (query.has('closeontrap')) wrapper.value?.querySelector<HTMLElement>('[data-modal-close]')?.click();
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
        <div ref="wrapper" role="dialog" aria-modal="true" :data-inertiaui-modal-entered="marker" :data-leaving="leaving || undefined" class="pointer-events-auto w-full max-w-lg rounded-xl border bg-background">
            <ModalPanel
                title="Rename"
                :form-mode="false"
                busy-label="Saving…"
                cancel-label="Cancel"
                :show-close-button="!query.has('noclose')"
                initial-focus="field"
                confirm-message="You have unsaved changes."
                :dirty="false"
                :full-screen="false"
                :checks="new Set()"
                @close="close"
            >
                <template v-if="withField && lateReady">
                    <input aria-label="Name" data-first-field class="h-9 rounded-md border px-3" />
                    <input aria-label="Note" data-second-field class="h-9 rounded-md border px-3" />
                </template>
                <p v-else-if="withField">Loading…</p>
                <p v-else>Nothing to fill in.</p>
            </ModalPanel>
        </div>
    </div>
</template>

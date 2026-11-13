<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';
import ModalPanel from '@/components/Kit/Overlay/ModalPanel.vue';

// A searchable single select for run.mjs (?select-query): a chosen label is never the search, Escape and a click outside drop the typed text, and an optional
// field clears by keyboard. `For` is optional (clearable by default), `Room` is required (no clear). `&modal` puts both in the kit Modal (a native <dialog>).
const inModal = new URLSearchParams(location.search).has('modal');
const people = ['Ada Lovelace', 'Grace Hopper', 'Katherine Johnson', 'Margaret Hamilton', 'Radia Perlman', 'Barbara Liskov', 'Frances Allen', 'Annie Easley'].map(
    (label) => ({ value: label.toLowerCase().replace(/ /g, '-'), label }),
);
const options = [{ value: 'public', label: 'Not a member (a public booking)' }, ...people];
const rooms = ['North', 'South', 'East', 'West', 'Attic', 'Cellar', 'Garden', 'Library', 'Studio'].map((label) => ({ value: label.toLowerCase(), label }));
const forWho = ref<string | null>('public');
const room = ref<string | null>('north');
const dialog = ref<HTMLDialogElement>();
const events = ((window as unknown as { kitEvents: string[] }).kitEvents = []);
onMounted(() => {
    if (!inModal) return;
    dialog.value?.addEventListener('close', () => events.push('dialog close'));
    dialog.value?.showModal();
});
</script>
<template>
    <main class="max-w-md p-4">
        <h1>Booking</h1>
        <form v-if="!inModal" class="grid gap-4" @submit.prevent>
            <Field id="for" label="For">
                <InputSelect id="for" v-model="forWho" name="for" :options="options" />
            </Field>
            <Field id="room" label="Room">
                <InputSelect id="room" v-model="room" name="room" :options="rooms" required />
            </Field>
        </form>
        <p data-outside class="mt-8">Somewhere else on the page</p>
        <output data-test-model>{{ JSON.stringify({ for: forWho, room }) }}</output>
    </main>
    <dialog v-if="inModal" ref="dialog" class="m-0 size-full max-h-none max-w-none bg-transparent p-0 backdrop:bg-black/75" data-package-dialog>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-lg overflow-hidden rounded-xl border bg-background">
                <ModalPanel
                    title="New booking"
                    :form-mode="false"
                    busy-label="Saving…"
                    cancel-label="Cancel"
                    :show-close-button="true"
                    initial-focus="field"
                    confirm-message="You have unsaved changes."
                    :dirty="true"
                    :full-screen="false"
                    :checks="new Set()"
                    @close="events.push('modal closed')"
                >
                    <div class="grid gap-4">
                        <Field id="for" label="For">
                            <InputSelect id="for" v-model="forWho" name="for" :options="options" />
                        </Field>
                        <Field id="room" label="Room">
                            <InputSelect id="room" v-model="room" name="room" :options="rooms" required />
                        </Field>
                        <p data-outside>Somewhere else in the modal</p>
                    </div>
                </ModalPanel>
            </div>
        </div>
    </dialog>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { DropdownMenu, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Select from '@/components/Kit/Form/Select.vue';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import ModalPanel from '@/components/Kit/Overlay/ModalPanel.vue';

// The kit Modal as @inertiaui/modal renders it by default: a native <dialog> opened with showModal() (the browser's top layer), the kit's panel inside.
const dialog = ref<HTMLDialogElement>();
const events = ((window as unknown as { kitEvents: string[] }).kitEvents = []);
const open = () => dialog.value?.showModal();
const closed = () => {
    events.push('modal closed');
    dialog.value?.close();
};
const status = ref('a');
onMounted(() => dialog.value?.addEventListener('close', () => events.push('dialog close')));
</script>
<template>
    <main class="p-8">
        <button type="button" data-open @click="open">Open</button>
        <p class="mt-4">The page behind the modal.</p>
        <output data-status>{{ status }}</output>
    </main>
    <dialog ref="dialog" class="m-0 size-full max-h-none max-w-none bg-transparent p-0 backdrop:bg-black/75" data-package-dialog>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-lg overflow-hidden rounded-xl border bg-background">
                <ModalPanel
                    title="Edit post"
                    :form-mode="false"
                    busy-label="Saving…"
                    cancel-label="Cancel"
                    :show-close-button="true"
                    initial-focus="field"
                    confirm-message="You have unsaved changes."
                    :dirty="true"
                    :full-screen="false"
                    :checks="new Set()"
                    @close="closed"
                >
                    <input aria-label="Title" class="h-9 rounded-md border px-3" />
                    <Select id="status" v-model="status" :options="[{ value: 'a', label: 'Draft' }, { value: 'b', label: 'Published' }]" />
                    <DropdownMenu>
                        <DropdownMenuTrigger data-menu-trigger>More</DropdownMenuTrigger>
                        <DropdownMenuContent><DropdownMenuItem data-menu-item @select="events.push('menu item')">Duplicate</DropdownMenuItem></DropdownMenuContent>
                    </DropdownMenu>
                </ModalPanel>
            </div>
        </div>
    </dialog>
</template>

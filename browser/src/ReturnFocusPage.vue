<script setup lang="ts">
import { nextTick, ref } from 'vue';
import MediaGallery from '@/components/Kit/Media/MediaGallery.vue';
import Modal from '@/components/Kit/Overlay/Modal.vue';

// Focus returning from a local kit Modal (select-modal.html, component Return/Base, on the real @inertiaui/modal-vue): a link opens a thumbnail-less
// MediaGallery's lightbox with show(id), its items arriving just before (empty, set, nextTick, show), as a feed's attachment link does; a button opens a plain
// Modal with v-model:open. Each closes by Escape, a click on Close or Enter on Close; the opener must hold focus once the panel has left.
const items = ref<{ id: string; name: string; url: string; mime: string }[]>(
    [],
);
const gallery = ref<{ show: (id: string) => void } | null>(null);
const pixel = 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
async function view() {
    items.value = [];
    await nextTick();
    items.value = [
        { id: 'a', name: 'Floor plan', url: pixel, mime: 'image/gif' },
    ];
    await nextTick();
    gallery.value?.show('a');
}
// A second attachment link on the same gallery, opening it again without emptying it (one viewer for every tile).
function viewAgain() {
    gallery.value?.show('a');
}
const open = ref(false);
</script>
<template>
    <div class="flex flex-col gap-4">
        <a id="return-link" href="#plan" @click.prevent="view">Floor plan</a>
        <a id="return-link-2" href="#plan-2" @click.prevent="viewAgain"
            >Floor plan again</a
        >
        <MediaGallery
            ref="gallery"
            label="Attachments"
            :items="items"
            :thumbnails="false"
        />
        <button id="return-button" type="button" @click="open = true">
            Details
        </button>
        <button id="return-elsewhere" type="button">Elsewhere</button>
        <Modal v-model:open="open" title="Details" size="sm"
            ><p>Some details.</p></Modal
        >
    </div>
</template>

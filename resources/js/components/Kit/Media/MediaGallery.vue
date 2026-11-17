<script setup lang="ts">
import { ChevronLeft, ChevronRight, FileText } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Modal from '@/components/Kit/Overlay/Modal.vue';

/** A file to show: an image opens in the lightbox, anything else opens as a link. `thumbnail` (or `thumb`) is the generated preview; until it exists a placeholder shows, never the original. */
export type GalleryItem = {
    id: string;
    name: string;
    url: string;
    mime?: string | null;
    mimeType?: string | null;
    thumbnail?: string | null;
    thumb?: string | null;
};

/*
 * A read-only row of thumbnails for mixed files (docs/media.md, "A gallery"): note or intake attachments with no upload. Images page through a lightbox
 * (`lightbox`, on by default; arrow keys and Previous/Next), other files open as links in a new tab. `maxThumbnails` caps the row with a "+N" tile that opens
 * the rest; `size` is small or medium; `filenames` shows each name under its tile. To add, remove or reorder, use MediaCollection.
 */
const props = withDefaults(
    defineProps<{
        items: GalleryItem[];
        label?: string;
        size?: 'small' | 'medium';
        maxThumbnails?: number | null;
        filenames?: boolean;
        lightbox?: boolean;
        readonly?: boolean;
        /** False hides the thumbnail row: a lightbox only, opened with the exposed `show(item or id)`. */
        thumbnails?: boolean;
    }>(),
    {
        label: 'Attachments',
        size: 'small',
        maxThumbnails: null,
        filenames: false,
        lightbox: true,
        readonly: true,
        thumbnails: true,
    },
);
const expanded = ref(false);
const shown = computed(() =>
    props.maxThumbnails && !expanded.value
        ? props.items.slice(0, props.maxThumbnails)
        : props.items,
);
const hidden = computed(() => props.items.length - shown.value.length);
const mimeOf = (item: GalleryItem) => item.mime ?? item.mimeType ?? '';
const isImage = (item: GalleryItem) => mimeOf(item).startsWith('image/');
const thumbOf = (item: GalleryItem) => item.thumbnail ?? item.thumb ?? null;
const images = computed(() => props.items.filter(isImage));
const open = ref(false);
const current = ref(0);
const viewing = computed(() => images.value[current.value]);
function show(target: GalleryItem | string) {
    const id = typeof target === 'string' ? target : target.id;
    const at = images.value.findIndex((image) => image.id === id);
    if (at < 0) return;
    current.value = at;
    // Opened from code there is no trigger for the dialog to hand focus back to, so it remembers what had focus and returns it on close.
    if (
        !open.value &&
        document.activeElement instanceof HTMLElement &&
        document.activeElement !== document.body
    )
        returnTo = document.activeElement;
    open.value = true;
}
let returnTo: HTMLElement | null = null;
watch(open, (isOpen) => {
    if (isOpen || !returnTo) return;
    const target = returnTo;
    returnTo = null;
    // After the dialog's own close focus (which lands on body without a trigger).
    requestAnimationFrame(() => {
        if (target.isConnected) target.focus();
    });
});
defineExpose({ show });
function step(by: number) {
    const count = images.value.length;
    current.value = (current.value + by + count) % count;
}
const box = computed(() => (props.size === 'small' ? 'size-16' : 'size-28'));
</script>
<template>
    <section :aria-label="label" data-media-gallery :data-size="size">
        <ul v-if="thumbnails" class="flex flex-wrap gap-2">
            <li
                v-for="item in shown"
                :key="item.id"
                :class="filenames ? 'grid gap-1' : ''"
                data-media-gallery-item
                :data-media-item="item.id"
            >
                <component
                    :is="isImage(item) && lightbox ? 'button' : 'a'"
                    :type="isImage(item) && lightbox ? 'button' : undefined"
                    :href="isImage(item) && lightbox ? undefined : item.url"
                    :target="isImage(item) && lightbox ? undefined : '_blank'"
                    :rel="isImage(item) && lightbox ? undefined : 'noopener'"
                    :aria-label="
                        isImage(item)
                            ? `View ${item.name}`
                            : `Open ${item.name}`
                    "
                    :class="[
                        box,
                        'border-border bg-muted text-muted-foreground focus-visible:outline-ring relative flex items-center justify-center overflow-hidden rounded-md border focus-visible:outline-2 focus-visible:outline-offset-2',
                    ]"
                    @click="isImage(item) && lightbox && show(item)"
                >
                    <img
                        v-if="thumbOf(item)"
                        :src="thumbOf(item)!"
                        alt=""
                        loading="lazy"
                        class="size-full object-cover"
                    />
                    <span
                        v-else-if="isImage(item)"
                        class="bg-muted size-full animate-pulse"
                        data-media-pending
                    />
                    <FileText v-else class="size-6" aria-hidden="true" />
                </component>
                <span
                    v-if="filenames"
                    :class="[
                        size === 'small' ? 'max-w-16' : 'max-w-28',
                        'truncate text-xs',
                    ]"
                    data-media-filename
                    >{{ item.name }}</span
                >
            </li>
            <li v-if="hidden > 0">
                <button
                    type="button"
                    :class="[
                        box,
                        'border-border bg-muted focus-visible:outline-ring flex items-center justify-center rounded-md border text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2',
                    ]"
                    :aria-label="`Show ${hidden} more`"
                    data-media-more
                    @click="expanded = true"
                >
                    +{{ hidden }}
                </button>
            </li>
        </ul>
        <Modal
            v-if="lightbox && images.length"
            v-model:open="open"
            :title="viewing?.name ?? label"
            :description="
                images.length > 1
                    ? `${current + 1} of ${images.length}`
                    : undefined
            "
            size="xl"
        >
            <div
                class="grid gap-3"
                data-media-lightbox
                tabindex="-1"
                @keydown.left="step(-1)"
                @keydown.right="step(1)"
            >
                <img
                    v-if="viewing"
                    :src="viewing.url"
                    :alt="viewing.name"
                    class="mx-auto max-h-[70vh] w-auto rounded-md object-contain"
                />
                <div
                    v-if="images.length > 1"
                    class="flex justify-between gap-2"
                >
                    <button
                        type="button"
                        class="hover:bg-accent inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm"
                        data-media-previous
                        @click="step(-1)"
                    >
                        <ChevronLeft class="size-4" aria-hidden="true" />
                        Previous
                    </button>
                    <button
                        type="button"
                        class="hover:bg-accent inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm"
                        data-media-next
                        @click="step(1)"
                    >
                        Next
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>

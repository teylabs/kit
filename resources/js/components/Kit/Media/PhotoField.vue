<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import BulkDrop from '@/components/Kit/Form/BulkDrop.vue';
import {
    directUpload,
    tooLarge,
    type DirectRoutes,
    type DirectTicketRoutes,
} from '@/components/Kit/Form/directUpload';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import Modal from '@/components/Kit/Overlay/Modal.vue';
import MediaThumbnail, {
    type MediaPicture,
} from '@/components/Kit/Media/MediaThumbnail.vue';
import PhotoTile from '@/components/Kit/Media/PhotoTile.vue';

/** A server action: where to send it. */
type Endpoint = { url: string; method: string; label?: string };
export type PhotoSource = {
    id: string;
    name: string;
    thumb?: string | null;
};

/*
 * One photo slot (a subject's main photo, a workspace logo): a tile with the current photo and Change, or an empty camera tile (PhotoTile). Change opens a
 * modal with (1) the current photo, large; (2) a drop, paste or browse zone that uploads the replacement straight to the bucket (`actions.replace`, directUpload)
 * and emits `replaced`; (3) the record's other photos (`sources`), where choosing one sends `{ media_id, version }` to `actions.choose` and emits `chosen`;
 * (4) Remove, destructive and confirmed, sent to `actions.remove` with the version, emitting `removed`. Each write carries `version`, so the server refuses a
 * stale one; choose and remove are Inertia visits, so the server's toast and fresh props come back and the modal closes on success. A missing action is absent.
 */
const props = withDefaults(
    defineProps<{
        photo: MediaPicture | null;
        label: string;
        accept: string[];
        maxBytes: number;
        actions?: {
            replace?: DirectRoutes | DirectTicketRoutes | null;
            choose?: Endpoint | null;
            remove?: Endpoint | null;
        };
        sources?: PhotoSource[];
        version?: string | number | null;
        aspect?: 'square' | '4/3';
    }>(),
    {
        actions: () => ({}),
        sources: () => [],
        version: null,
        aspect: 'square',
    },
);
const emit = defineEmits<{
    replaced: [item: unknown];
    chosen: [id: string];
    removed: [];
}>();
const open = ref(false);
const confirming = ref(false);
const working = ref(false);
const progress = ref<number | null>(null);
const error = ref('');
const canChange = computed(
    () =>
        !!(
            props.actions.replace ||
            (props.actions.choose && props.sources.length) ||
            props.actions.remove
        ),
);
const replaceRoutes = computed(() => {
    const routes = props.actions.replace;
    if (!routes) return null;
    return 'ticket' in routes
        ? { ...routes, maxBytes: routes.maxBytes ?? props.maxBytes }
        : routes;
});
async function replace(files: File[]) {
    const file = files[0];
    if (!file || !replaceRoutes.value) return;
    error.value = tooLarge(file, props.maxBytes) ?? '';
    if (error.value) return;
    working.value = true;
    progress.value = 0;
    try {
        const result = await directUpload(
            file,
            replaceRoutes.value,
            (fraction) => (progress.value = fraction),
        );
        emit('replaced', result);
        open.value = false;
        router.reload();
    } catch (failure) {
        error.value =
            failure instanceof Error ? failure.message : 'Upload failed';
    } finally {
        working.value = false;
        progress.value = null;
    }
}
function visit(
    action: Endpoint,
    data: Record<string, unknown>,
    done: () => void,
) {
    working.value = true;
    error.value = '';
    router.visit(action.url, {
        method: action.method.toLowerCase() as
            'post' | 'put' | 'patch' | 'delete',
        data: { ...data, version: props.version },
        preserveScroll: true,
        onSuccess: () => {
            done();
            open.value = false;
        },
        onError: (errors) => {
            error.value = Object.values(errors)[0] ?? 'Not saved';
        },
        onFinish: () => (working.value = false),
    });
}
function choose(source: PhotoSource) {
    if (props.actions.choose)
        visit(props.actions.choose, { media_id: source.id }, () =>
            emit('chosen', source.id),
        );
}
function remove() {
    confirming.value = false;
    if (props.actions.remove)
        visit(props.actions.remove, {}, () => emit('removed'));
}
</script>
<template>
    <div
        class="grid max-w-48 gap-2"
        data-photo-field
        :data-has-photo="!!photo || undefined"
    >
        <template v-if="photo">
            <button
                type="button"
                :class="[
                    aspect === 'square' ? 'aspect-square' : 'aspect-4/3',
                    'border-border bg-muted focus-visible:outline-ring w-full overflow-hidden rounded-lg border focus-visible:outline-2 focus-visible:outline-offset-2',
                ]"
                :aria-label="`Change ${label}`"
                :disabled="!canChange"
                data-photo-field-tile
                @click="open = true"
            >
                <img
                    v-if="photo.thumb"
                    :src="photo.thumb"
                    :srcset="
                        photo.card
                            ? `${photo.thumb} 400w, ${photo.card} 800w`
                            : undefined
                    "
                    sizes="12rem"
                    :alt="label"
                    class="size-full object-cover"
                />
                <span
                    v-else
                    class="bg-muted block size-full animate-pulse"
                    data-media-pending
                />
            </button>
            <Button
                v-if="canChange"
                variant="secondary"
                size="sm"
                data-photo-field-change
                @click="open = true"
                >Change</Button
            >
        </template>
        <PhotoTile
            v-else-if="replaceRoutes"
            :label="label"
            :accept="accept"
            :max-bytes="maxBytes"
            :aspect="aspect"
            :disabled="working"
            @files="replace"
        />
        <MediaThumbnail v-else :picture="null" :alt="label" size="lg" />
        <p
            v-if="error && !open"
            role="alert"
            class="text-destructive-text text-sm"
        >
            {{ error }}
        </p>
        <Modal v-model:open="open" :title="`Change ${label}`" size="md">
            <div class="grid gap-5" data-photo-field-modal>
                <img
                    v-if="photo?.card ?? photo?.thumb"
                    :src="(photo?.card ?? photo?.thumb)!"
                    :alt="`Current ${label}`"
                    class="border-border mx-auto max-h-72 rounded-md border object-contain"
                    data-photo-field-current
                />
                <BulkDrop
                    v-if="replaceRoutes"
                    name="photo"
                    :label="label"
                    :accept="accept"
                    :max-bytes="maxBytes"
                    :has-files="false"
                    :disabled="working"
                    :busy="
                        progress === null
                            ? undefined
                            : `Uploading… ${Math.round(progress * 100)}%`
                    "
                    prompt="Drop, paste or browse to replace"
                    handle-drops
                    @files="replace"
                />
                <section
                    v-if="actions.choose && sources.length"
                    class="grid gap-2"
                    aria-label="Choose from existing photos"
                    data-photo-field-sources
                >
                    <h3 class="text-sm font-medium">Or choose one</h3>
                    <ul
                        class="grid grid-cols-[repeat(auto-fill,minmax(5rem,1fr))] gap-2"
                    >
                        <li v-for="source in sources" :key="source.id">
                            <button
                                type="button"
                                class="border-border bg-muted focus-visible:outline-ring aspect-square w-full overflow-hidden rounded-md border focus-visible:outline-2 focus-visible:outline-offset-2"
                                :aria-label="`Use ${source.name}`"
                                :disabled="working"
                                :data-photo-field-source="source.id"
                                @click="choose(source)"
                            >
                                <img
                                    v-if="source.thumb"
                                    :src="source.thumb"
                                    alt=""
                                    loading="lazy"
                                    class="size-full object-cover"
                                />
                            </button>
                        </li>
                    </ul>
                </section>
                <p
                    v-if="error"
                    role="alert"
                    class="text-destructive-text text-sm"
                >
                    {{ error }}
                </p>
            </div>
            <template #footer>
                <Button
                    v-if="actions.remove && photo"
                    variant="destructive"
                    :disabled="working"
                    data-photo-field-remove
                    @click="confirming = true"
                    >{{ actions.remove.label ?? 'Remove' }}</Button
                >
                <Button
                    variant="secondary"
                    data-photo-field-done
                    @click="open = false"
                    >Done</Button
                >
            </template>
        </Modal>
        <Dialog
            v-model:open="confirming"
            :title="`Remove ${label}?`"
            description="The photo is taken off this slot."
            confirm-label="Remove"
            variant="danger"
            @confirm="remove"
        />
    </div>
</template>

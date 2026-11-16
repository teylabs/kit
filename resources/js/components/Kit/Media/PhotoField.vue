<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';
import { actionIcons } from '@/components/Kit/Action/actionIcons';
import Card from '@/components/Kit/Card/Card.vue';
import CardFooter from '@/components/Kit/Card/CardFooter.vue';
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import type { MediaTileAction } from '@/components/Kit/Media/MediaCollection.vue';
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

/** A server action: where to send it. `confirm` (remove) words the confirmation, as an Action's does. */
type Endpoint = {
    url: string;
    method: string;
    label?: string;
    confirm?: {
        title?: string | null;
        description?: string | null;
        label?: string | null;
    } | null;
};
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
 * `replace` also takes a plain `{ url, method }` (the kit posts the file as multipart, field `photo`, with `version`, as an Inertia visit) or `true` (the kit
 * only emits `files` and the host posts them). Every replace emits `files` first. `size` widens the slot (`fill` takes its container's width and height),
 * `title` names the modal (default "Change main photo"), and `actions.remove.confirm` words the Remove confirmation.
 * `variant="tile"` draws the standard photo tile (MediaCollection's `tiles` card): the photo with a `badge`, and a footer with the label, Change and a ⋯ menu
 * holding Remove (confirmed) and the host's `tile-actions`, emitted as `action(key)`. The root keeps to its content's height in a taller grid row.
 */
/** "Main photo" inside a sentence: "main photo". An acronym ("PDF cover") keeps its case. */
function inSentence(label: string): string {
    return /^[A-Z][A-Z]/.test(label)
        ? label
        : label.charAt(0).toLowerCase() + label.slice(1);
}
const props = withDefaults(
    defineProps<{
        photo: MediaPicture | null;
        label: string;
        accept: string[];
        maxBytes: number;
        actions?: {
            replace?:
                DirectRoutes | DirectTicketRoutes | Endpoint | true | null;
            choose?: Endpoint | null;
            remove?: Endpoint | null;
        };
        sources?: PhotoSource[];
        version?: string | number | null;
        aspect?: 'square' | '4/3';
        /** `sm` (12rem, the default), `md` (18rem), `lg` (24rem), or `fill`: its container's width and height (a feature photo over a grid). */
        size?: 'sm' | 'md' | 'lg' | 'fill';
        /** The modal's title. Default: "Change " and the label inside the sentence. */
        title?: string | null;
        /** `field` (the default): the photo with a Change button under it. `tile`: the standard photo tile, a card with a footer and a ⋯ menu. */
        variant?: 'field' | 'tile';
        /** `tile`: a badge drawn on the photo, e.g. "Main photo". */
        badge?: string | null;
        /** `tile`: the host's own ⋯ menu rows, after Remove, emitted as `action(key)`. */
        tileActions?: MediaTileAction[];
    }>(),
    {
        actions: () => ({}),
        sources: () => [],
        version: null,
        aspect: 'square',
        size: 'sm',
        title: null,
        variant: 'field',
        badge: null,
        tileActions: () => [],
    },
);
const emit = defineEmits<{
    files: [files: File[]];
    replaced: [item: unknown];
    chosen: [id: string];
    removed: [];
    action: [key: string];
}>();
const open = ref(false);
const confirming = ref(false);
const confirmingTile = ref(false);
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
    if (!routes || routes === true || 'url' in routes) return null;
    return 'ticket' in routes
        ? { ...routes, maxBytes: routes.maxBytes ?? props.maxBytes }
        : routes;
});
const replaceEndpoint = computed(() => {
    const routes = props.actions.replace;
    return routes && routes !== true && 'url' in routes ? routes : null;
});
const canReplace = computed(() => !!props.actions.replace);
const modalTitle = computed(
    () => props.title ?? `Change ${inSentence(props.label)}`,
);
const removeConfirm = computed(() => {
    const confirm = props.actions.remove?.confirm;
    return {
        title: confirm?.title ?? `Remove ${props.label}?`,
        description:
            confirm?.description ?? 'The photo is taken off this slot.',
        label: confirm?.label ?? 'Remove',
    };
});
const iconFor = (action: MediaTileAction) =>
    typeof action.icon === 'string'
        ? (actionIcons[action.icon] ?? null)
        : (action.icon ?? null);
const hasMenu = computed(
    () =>
        !!(props.actions.remove && props.photo) || props.tileActions.length > 0,
);
const sized = {
    sm: 'max-w-48',
    md: 'max-w-72',
    lg: 'max-w-96',
    fill: 'size-full grid-rows-[minmax(0,1fr)_auto]',
} as const;
async function replace(files: File[]) {
    const file = files[0];
    if (!file || !canReplace.value) return;
    error.value = tooLarge(file, props.maxBytes) ?? '';
    if (error.value) return;
    emit('files', [file]);
    if (replaceEndpoint.value) {
        visit(
            replaceEndpoint.value,
            { photo: file },
            () => emit('replaced', file),
            true,
        );
        return;
    }
    if (!replaceRoutes.value) {
        open.value = false;
        return;
    }
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
    multipart = false,
) {
    working.value = true;
    error.value = '';
    router.visit(action.url, {
        method: action.method.toLowerCase() as
            'post' | 'put' | 'patch' | 'delete',
        data: { ...data, version: props.version },
        preserveScroll: true,
        ...(multipart ? { forceFormData: true } : {}),
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
    confirmingTile.value = false;
    if (props.actions.remove)
        visit(props.actions.remove, {}, () => emit('removed'));
}
</script>
<template>
    <div
        :class="[
            'grid gap-2',
            size !== 'fill' && 'content-start self-start',
            sized[size],
        ]"
        :data-size="size"
        :data-variant="variant"
        data-photo-field
        :data-has-photo="!!photo || undefined"
    >
        <Card
            v-if="photo && variant === 'tile'"
            as="div"
            :class="[
                'flex flex-col overflow-hidden',
                size === 'fill' && 'h-full',
            ]"
            data-photo-field-card
        >
            <div
                :class="[
                    'relative isolate',
                    size === 'fill'
                        ? 'min-h-0 flex-1'
                        : aspect === 'square'
                          ? 'aspect-square'
                          : 'aspect-4/3',
                ]"
            >
                <button
                    type="button"
                    class="bg-muted focus-visible:outline-ring block size-full focus-visible:outline-2 focus-visible:-outline-offset-2"
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
                        :sizes="size === 'sm' ? '12rem' : '24rem'"
                        :alt="label"
                        class="size-full object-cover"
                    />
                    <span
                        v-else
                        class="bg-muted block size-full animate-pulse"
                        data-media-pending
                    />
                </button>
                <span
                    v-if="badge"
                    class="bg-primary text-primary-foreground pointer-events-none absolute top-1.5 left-1.5 z-10 rounded-sm px-1.5 text-xs leading-5 font-medium shadow-sm"
                    data-photo-field-badge
                    >{{ badge }}</span
                >
            </div>
            <CardFooter class="shrink-0 px-1! py-0.5!" data-photo-field-footer>
                <div class="flex min-w-0 items-center gap-0.5">
                    <span
                        class="min-w-0 flex-1 truncate px-1 text-xs"
                        data-photo-field-label
                        >{{ label }}</span
                    >
                    <Button
                        v-if="canChange"
                        variant="ghost"
                        size="sm"
                        class="h-7 px-2 text-xs"
                        data-photo-field-change
                        @click="open = true"
                        >Change</Button
                    >
                    <DropdownMenu v-if="hasMenu" :modal="false">
                        <DropdownMenuTrigger as-child
                            ><button
                                type="button"
                                data-photo-field-menu
                                class="hover:bg-accent text-muted-foreground hover:text-foreground focus-visible:outline-ring inline-flex size-7 shrink-0 items-center justify-center rounded-md focus-visible:outline-2"
                                :aria-label="`Actions for ${label}`"
                            >
                                <MoreHorizontal
                                    class="size-4"
                                    aria-hidden="true"
                                /></button
                        ></DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                v-for="action in tileActions"
                                :key="action.key"
                                :class="
                                    action.destructive &&
                                    'text-destructive-text focus:text-destructive-text'
                                "
                                v-bind="action.attrs ?? {}"
                                :data-photo-field-action="action.key"
                                @select="emit('action', action.key)"
                                ><component
                                    :is="iconFor(action)!"
                                    v-if="iconFor(action)"
                                    class="size-4"
                                    aria-hidden="true"
                                />{{ action.label }}</DropdownMenuItem
                            >
                            <template v-if="actions.remove">
                                <DropdownMenuSeparator
                                    v-if="tileActions.length"
                                />
                                <DropdownMenuItem
                                    class="text-destructive-text focus:text-destructive-text"
                                    :disabled="working"
                                    data-photo-field-menu-remove
                                    @select="confirmingTile = true"
                                    >{{
                                        actions.remove.label ?? 'Remove'
                                    }}</DropdownMenuItem
                                >
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </CardFooter>
        </Card>
        <template v-else-if="photo">
            <button
                type="button"
                :class="[
                    size === 'fill'
                        ? 'h-full'
                        : aspect === 'square'
                          ? 'aspect-square'
                          : 'aspect-4/3',
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
                    :sizes="size === 'sm' ? '12rem' : '24rem'"
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
            v-else-if="canReplace"
            :label="label"
            :accept="accept"
            :max-bytes="maxBytes"
            :aspect="size === 'fill' ? 'fill' : aspect"
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
        <Dialog
            v-if="variant === 'tile'"
            v-model:open="confirmingTile"
            :title="removeConfirm.title"
            :description="removeConfirm.description"
            :confirm-label="removeConfirm.label"
            variant="danger"
            @confirm="remove"
        />
        <Modal v-model:open="open" :title="modalTitle" size="md">
            <div class="grid gap-5" data-photo-field-modal>
                <!-- Inside the Modal, so its overlay renders into the Modal's <dialog>, over it (layer.ts). -->
                <Dialog
                    v-model:open="confirming"
                    :title="removeConfirm.title"
                    :description="removeConfirm.description"
                    :confirm-label="removeConfirm.label"
                    variant="danger"
                    @confirm="remove"
                />
                <img
                    v-if="photo?.card ?? photo?.thumb"
                    :src="(photo?.card ?? photo?.thumb)!"
                    :alt="`Current ${label}`"
                    class="border-border mx-auto max-h-72 rounded-md border object-contain"
                    data-photo-field-current
                />
                <BulkDrop
                    v-if="canReplace"
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
    </div>
</template>

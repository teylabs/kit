<script setup lang="ts">
import { ArrowLeft, ArrowRight, Check, MoreHorizontal } from '@lucide/vue';
import { computed, type Component } from 'vue';
import type { MenuSubject } from '@/components/Kit/Menu/menuSubject';
import { actionIcons } from '@/components/Kit/Action/actionIcons';
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import SortableList from '@/components/Kit/Data/SortableList.vue';
import SortableHandle from '@/components/Kit/Data/SortableHandle.vue';
import MiddleTruncate from '@/components/Kit/Data/MiddleTruncate.vue';
import Card from '@/components/Kit/Card/Card.vue';
import CardFooter from '@/components/Kit/Card/CardFooter.vue';
import type { ReorderAction } from '@/components/Kit/Data/sortable';
import BulkDrop from '@/components/Kit/Form/BulkDrop.vue';
import type { DirectRoutes } from '@/components/Kit/Form/directUpload';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';
import PhotoTile from '@/components/Kit/Media/PhotoTile.vue';

export type MediaItem = {
    id: string;
    name: string;
    mime: string;
    size?: number | null;
    src: string;
    srcset?: string | null;
    width?: number | null;
    height?: number | null;
    /** The generated thumbnail; in `tiles`, a tile without one shows a placeholder until it exists (`pending`), never the original. */
    thumb?: string | null;
    pending?: boolean;
    /** `tiles`: the tile footer's text in place of `name`, e.g. the photo's role ("Feature", "Photo 2"). The `tile-label` slot wins over it. */
    label?: string | null;
    /** `tiles`: the caption under the file name in the ⋯ menu's subject header, e.g. "Photo 3 · in report". */
    caption?: string | null;
};
/** A tile's own action, emitted as `action(key, item)` for the host to run. */
export type MediaTileAction = {
    key: string;
    label: string;
    destructive?: boolean;
    /** An actionIcons name (kebab case) or a component, drawn before the label. */
    icon?: string | Component | null;
    /** Bound on the menu row, e.g. a test hook. */
    attrs?: Record<string, string | boolean>;
};
export type MediaCollectionData = {
    collection: string;
    version: string;
    items: MediaItem[];
};

/*
 * One record's photos or files (docs/media.md, "A record's collection"), from the server's MediaCollection::for: a single file (`single`) or an ordered set.
 * Files are added with BulkDrop's direct upload (`direct`), so no bytes go through the form. An ordered set reorders with SortableList (drag, keys and Move up/down
 * buttons), sent with `reorder` and the collection's version, and rolls back on a stale one (`conflict`). Each item's menu moves it to another collection
 * (`move-targets`) and removes it; those are emitted for the host to run (its own route, which calls MoveMedia / RemoveMedia, and a confirmation for remove).
 * Words come from `labels`. Nothing here decides what a record may hold: the server's rules do.
 * `layout="tiles"` is the photo strip: square (or `tile-aspect="4/3"`) cards in the server's order, each with a placeholder until its `thumb` exists, a corner
 * badge (`badges`), a check (`selected-ids`), dimmed (`dim-ids`) and its own ⋯ actions (`tile-actions`, emitted as `action(key, item)`); a click emits `show`.
 * A tile's footer shows `item.label` (or the `#tile-label="{ item, index }"` slot), else the name; its ⋯ menu opens with the file name as the menu's subject
 * header (cut in the middle, the extension kept) and `item.caption` under it.
 * The add tile and the empty state are a dashed camera tile (PhotoTile); `empty="photo-tile"` draws that in the other layouts too.
 */
const props = withDefaults(
    defineProps<{
        collection: MediaCollectionData;
        label: string;
        accept: string[];
        maxBytes: number;
        single?: boolean;
        direct?: DirectRoutes | null;
        /** The host uploads: the add tile or drop shows without `direct` and only emits `files` (the kit uploads nothing). */
        hostUpload?: boolean;
        reorder?: ReorderAction | null;
        moveTargets?: { collection: string; label: string }[];
        disabled?: boolean;
        labels?: Partial<
            Record<
                | 'item'
                | 'empty'
                | 'actions'
                | 'remove'
                | 'moveTo'
                | 'selected'
                | 'earlier'
                | 'later',
                string
            >
        >;
        /** `grid` lays photos out as tiles (the image above its name and menu), as SortableList's layout. */
        layout?: 'list' | 'grid' | 'row' | 'tiles';
        /** `tiles`: each photo a square (or 4/3) card with its name, a corner badge, a check when selected, dimmed, and its own actions. */
        tileAspect?: 'square' | '4/3';
        selectedIds?: string[];
        badges?: Record<string, string>;
        dimIds?: string[];
        tileActions?:
            MediaTileAction[] | ((item: MediaItem) => MediaTileAction[]);
        /** `photo-tile` draws the empty collection (and the add tile, in `tiles`) as a dashed camera tile instead of text and a dropzone. */
        empty?: 'text' | 'photo-tile';
        addLabel?: string;
        /** `tiles`: disables the add tile and shows progress on it (a string is the progress text). */
        busy?: boolean | string;
        maxFiles?: number;
        /** `tiles` without `reorder`: the tiles still move (grip, keys, buttons) and `move(from, to)` is emitted for the host to save. */
        sortable?: boolean;
        /** Numbering starts at 1 + this (a strip continued from an earlier one). */
        indexOffset?: number;
        /** A divider after this many photos, e.g. the ones a listing shows. */
        cutAfter?: number | null;
        cutLabel?: string;
    }>(),
    {
        layout: 'list',
        tileAspect: 'square',
        selectedIds: () => [],
        badges: () => ({}),
        dimIds: () => [],
        tileActions: () => [],
        empty: 'text',
        addLabel: 'Add photos',
        busy: false,
        maxFiles: undefined,
        sortable: false,
        indexOffset: 0,
        cutAfter: null,
        cutLabel: undefined,
        single: false,
        direct: null,
        reorder: null,
        moveTargets: () => [],
        disabled: false,
        labels: () => ({}),
    },
);
const emit = defineEmits<{
    uploaded: [results: unknown[]];
    move: [item: MediaItem, to: string];
    remove: [item: MediaItem];
    saved: [version: string | number | null];
    conflict: [order: string[]];
    action: [key: string, item: MediaItem];
    show: [item: MediaItem];
    files: [files: File[]];
    reordered: [from: number, to: number];
}>();
defineSlots<{
    /** `tiles`: the tile footer's text in place of `item.label` and the name. */
    'tile-label'?(props: { item: MediaItem; index: number }): unknown;
}>();
const subjectFor = (item: MediaItem): MenuSubject => ({
    title: item.name,
    caption: item.caption ?? null,
    kind: 'file',
});
const iconFor = (action: MediaTileAction) =>
    typeof action.icon === 'string'
        ? (actionIcons[action.icon] ?? null)
        : (action.icon ?? null);
const tileSortable = computed(() => !!props.reorder || props.sortable);
const tiles = computed(() => props.layout === 'tiles');
// In tiles, the add tile is the grid's last cell (SortableList's #after), so it is exactly a photo tile's size.
const listLayout = computed(() =>
    props.layout === 'tiles' ? 'grid' : props.layout,
);
const actionsFor = (item: MediaItem) =>
    typeof props.tileActions === 'function'
        ? props.tileActions(item)
        : props.tileActions;
const photoTile = computed(() => props.empty === 'photo-tile' || tiles.value);
const text = computed(() => ({
    item: 'Photo',
    empty: 'Nothing here yet.',
    actions: 'Actions for',
    remove: 'Remove',
    moveTo: 'Move to',
    selected: 'Selected',
    earlier: 'Move earlier',
    later: 'Move later',
    ...props.labels,
}));
const items = computed(() => props.collection.items);
const targets = computed(() =>
    props.moveTargets.filter(
        (target) => target.collection !== props.collection.collection,
    ),
);
// A single-file collection takes one more file only while it is empty; replacing is a remove, then an add.
const canAdd = computed(
    () =>
        !props.disabled &&
        (!!props.direct || props.hostUpload) &&
        (!props.single || items.value.length === 0),
);
const inGrid = computed(
    () =>
        tiles.value && canAdd.value && !props.single && items.value.length > 0,
);
const itemLabel = (item: MediaItem, index: number) =>
    `${text.value.item} ${index + 1 + props.indexOffset}${item.name ? ` (${item.name})` : ''}`;
</script>
<template>
    <section
        class="grid gap-3"
        :aria-label="label"
        :data-media-collection="collection.collection"
    >
        <EmptyState
            v-if="items.length === 0 && !(photoTile && canAdd)"
            compact
            align="start"
            icon="image"
            :title="text.empty"
            data-media-empty
        />
        <SortableList
            v-else-if="!single && items.length"
            :items="items"
            :item-label="itemLabel"
            :label="label"
            :reorder="reorder"
            :version="collection.version"
            :disabled="disabled || (tiles && !tileSortable)"
            :layout="listLayout"
            :move-buttons="tiles ? tileSortable : undefined"
            :grip-in-slot="tiles"
            @moved="(from, to) => emit('reordered', from, to)"
            :cut-after="cutAfter"
            :cut-label="cutLabel"
            @saved="emit('saved', $event)"
            @conflict="emit('conflict', $event)"
        >
            <template #default="{ item, index, grip, move, first, last }">
                <Card
                    v-if="tiles"
                    as="div"
                    :class="[
                        tileAspect === 'square'
                            ? 'aspect-square'
                            : 'aspect-4/3',
                        'flex flex-col overflow-hidden',
                        selectedIds.includes(item.id) && 'ring-primary ring-2',
                    ]"
                    :data-media-item="item.id"
                    data-media-tile
                    :data-selected="selectedIds.includes(item.id) || undefined"
                    :data-dim="dimIds.includes(item.id) || undefined"
                >
                    <div class="relative isolate min-h-0 flex-1">
                        <button
                            type="button"
                            class="focus-visible:outline-ring block size-full focus-visible:outline-2 focus-visible:-outline-offset-2"
                            :aria-label="`View ${itemLabel(item, index)}`"
                            :aria-pressed="
                                selectedIds.length
                                    ? selectedIds.includes(item.id)
                                    : undefined
                            "
                            data-media-view
                            @click="emit('show', item)"
                        >
                            <img
                                v-if="item.thumb && !item.pending"
                                :src="item.thumb"
                                :alt="item.name"
                                class="size-full object-cover"
                                :class="
                                    dimIds.includes(item.id) && 'opacity-60'
                                "
                                loading="lazy"
                                decoding="async"
                                draggable="false"
                            />
                            <span
                                v-else
                                class="bg-muted/30 block size-full animate-pulse"
                                data-media-pending
                            />
                        </button>
                        <span
                            v-if="badges[item.id]"
                            class="bg-primary text-primary-foreground pointer-events-none absolute top-1.5 left-1.5 z-10 rounded-sm px-1.5 text-xs leading-5 font-medium shadow-(--kit-elevation-1)"
                            data-media-badge
                            >{{ badges[item.id] }}</span
                        >
                        <span
                            v-if="selectedIds.includes(item.id)"
                            class="bg-primary text-primary-foreground pointer-events-none absolute top-1.5 right-1.5 inline-flex size-5 items-center justify-center rounded-full shadow-(--kit-elevation-1)"
                            data-media-selected
                            ><Check class="size-3.5" aria-hidden="true" /><span
                                class="sr-only"
                                >{{ text.selected }}</span
                            ></span
                        >
                    </div>
                    <CardFooter
                        class="shrink-0 px-1! py-0.5!"
                        data-media-tile-footer
                    >
                        <div class="flex min-w-0 items-center gap-0.5">
                            <SortableHandle
                                v-if="tileSortable && !disabled"
                                :label="grip.label"
                                :disabled="grip.disabled"
                                class="size-7!"
                                data-media-grip
                                @keydown="grip.onKeydown"
                            />
                            <span
                                v-if="$slots['tile-label'] || item.label"
                                class="min-w-0 flex-1 truncate px-1 text-xs"
                                data-media-tile-label
                                ><slot
                                    name="tile-label"
                                    :item="item"
                                    :index="index"
                                    >{{ item.label }}</slot
                                ></span
                            >
                            <MiddleTruncate
                                v-else
                                :value="item.name"
                                kind="file"
                                class="min-w-0 flex-1 px-1 text-xs"
                                data-media-tile-name
                                data-media-tile-label
                            />
                            <DropdownMenu
                                :modal="false"
                                v-if="
                                    !disabled &&
                                    (actionsFor(item).length ||
                                        (tileSortable && items.length > 1))
                                "
                            >
                                <DropdownMenuTrigger as-child
                                    ><button
                                        type="button"
                                        data-media-menu
                                        class="hover:bg-accent text-muted-foreground hover:text-foreground focus-visible:outline-ring inline-flex size-7 shrink-0 items-center justify-center rounded-md focus-visible:outline-2"
                                        :aria-label="`${text.actions} ${itemLabel(item, index)}`"
                                    >
                                        <MoreHorizontal
                                            class="size-4"
                                            aria-hidden="true"
                                        /></button
                                ></DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    :subject="subjectFor(item)"
                                >
                                    <template
                                        v-if="tileSortable && items.length > 1"
                                    >
                                        <DropdownMenuItem
                                            v-if="!first"
                                            data-media-move="earlier"
                                            @select="move(-1, $event)"
                                            ><ArrowLeft
                                                class="size-4"
                                                aria-hidden="true"
                                            />{{
                                                text.earlier
                                            }}</DropdownMenuItem
                                        >
                                        <DropdownMenuItem
                                            v-if="!last"
                                            data-media-move="later"
                                            @select="move(1, $event)"
                                            ><ArrowRight
                                                class="size-4"
                                                aria-hidden="true"
                                            />{{ text.later }}</DropdownMenuItem
                                        >
                                        <DropdownMenuSeparator
                                            v-if="actionsFor(item).length"
                                        />
                                    </template>
                                    <DropdownMenuItem
                                        v-for="action in actionsFor(item)"
                                        :key="action.key"
                                        :class="
                                            action.destructive &&
                                            'text-destructive-text focus:text-destructive-text'
                                        "
                                        v-bind="action.attrs ?? {}"
                                        :data-media-action="action.key"
                                        @select="
                                            emit('action', action.key, item)
                                        "
                                        ><component
                                            :is="iconFor(action)!"
                                            v-if="iconFor(action)"
                                            class="size-4"
                                            aria-hidden="true"
                                            data-media-action-icon
                                        />{{ action.label }}</DropdownMenuItem
                                    >
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </CardFooter>
                </Card>
                <div
                    v-else
                    :class="
                        layout === 'list'
                            ? 'flex min-w-0 items-center gap-3'
                            : 'grid min-w-0 grid-cols-[1fr_auto] items-center gap-2'
                    "
                    :data-media-item="item.id"
                >
                    <img
                        :src="item.thumb ?? item.src"
                        :srcset="
                            item.thumb ? undefined : item.srcset || undefined
                        "
                        :sizes="layout === 'list' ? '4rem' : '10rem'"
                        :width="item.width ?? undefined"
                        :height="item.height ?? undefined"
                        :alt="itemLabel(item, index)"
                        loading="lazy"
                        :class="
                            layout === 'list'
                                ? 'border-border size-16 shrink-0 rounded-md border object-cover'
                                : 'border-border col-span-2 aspect-square w-full rounded-md border object-cover'
                        "
                    />
                    <span class="min-w-0 flex-1 truncate text-sm">{{
                        item.name
                    }}</span>
                    <DropdownMenu v-if="!disabled">
                        <DropdownMenuTrigger as-child
                            ><button
                                type="button"
                                class="hover:bg-accent focus-visible:outline-ring inline-flex size-8 items-center justify-center rounded-md focus-visible:outline-2"
                                :aria-label="`${text.actions} ${itemLabel(item, index)}`"
                            >
                                <MoreHorizontal
                                    class="size-4"
                                    aria-hidden="true"
                                /></button
                        ></DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                v-for="target in targets"
                                :key="target.collection"
                                :data-media-move="target.collection"
                                @select="emit('move', item, target.collection)"
                                >{{ text.moveTo }}
                                {{ target.label }}</DropdownMenuItem
                            >
                            <DropdownMenuItem
                                class="text-destructive-text focus:text-destructive-text"
                                data-media-remove
                                @select="emit('remove', item)"
                                >{{ text.remove }}</DropdownMenuItem
                            >
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </template>
            <template v-if="inGrid" #after>
                <PhotoTile
                    :name="collection.collection"
                    :label="items.length ? addLabel : label"
                    :accept="accept"
                    :max-bytes="maxBytes"
                    :multiple="!single"
                    :aspect="tileAspect"
                    :max-files="maxFiles"
                    :busy="busy"
                    :disabled="!!busy"
                    :direct="direct ?? undefined"
                    @files="emit('files', $event)"
                    @uploaded="emit('uploaded', $event)"
                />
            </template>
        </SortableList>
        <div
            v-else-if="items.length"
            class="flex min-w-0 items-center gap-3"
            :data-media-item="items[0].id"
        >
            <img
                :src="items[0].src"
                :srcset="items[0].srcset || undefined"
                sizes="8rem"
                :width="items[0].width ?? undefined"
                :height="items[0].height ?? undefined"
                :alt="label"
                class="border-border size-32 shrink-0 rounded-md border object-cover"
            />
            <div v-if="!disabled" class="flex flex-wrap gap-2">
                <button
                    v-for="target in targets"
                    :key="target.collection"
                    type="button"
                    class="text-sm underline underline-offset-2"
                    :data-media-move="target.collection"
                    @click="emit('move', items[0], target.collection)"
                >
                    {{ text.moveTo }} {{ target.label }}
                </button>
                <button
                    type="button"
                    class="text-destructive-text text-sm underline underline-offset-2"
                    data-media-remove
                    @click="emit('remove', items[0])"
                >
                    {{ text.remove }}
                </button>
            </div>
        </div>
        <PhotoTile
            v-if="canAdd && photoTile && !inGrid"
            :class="tiles ? 'max-w-48' : 'max-w-64'"
            :name="collection.collection"
            :label="items.length ? addLabel : label"
            :accept="accept"
            :max-bytes="maxBytes"
            :multiple="!single"
            :aspect="tileAspect"
            :max-files="maxFiles"
            :busy="busy"
            :disabled="!!busy"
            :direct="direct ?? undefined"
            @files="emit('files', $event)"
            @uploaded="emit('uploaded', $event)"
        />
        <BulkDrop
            v-else-if="canAdd && !inGrid"
            :name="collection.collection"
            :label="label"
            :accept="accept"
            :max-bytes="maxBytes"
            :has-files="items.length > 0"
            :multiple="!single"
            :direct="direct ?? undefined"
            compact
            @files="hostUpload && emit('files', $event)"
            @uploaded="emit('uploaded', $event)"
        />
    </section>
</template>

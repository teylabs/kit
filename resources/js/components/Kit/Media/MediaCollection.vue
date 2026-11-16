<script setup lang="ts">
import { Check, MoreHorizontal } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import SortableList from '@/components/Kit/Data/SortableList.vue';
import type { ReorderAction } from '@/components/Kit/Data/sortable';
import BulkDrop from '@/components/Kit/Form/BulkDrop.vue';
import type { DirectRoutes } from '@/components/Kit/Form/directUpload';
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
};
/** A tile's own action, emitted as `action(key, item)` for the host to run. */
export type MediaTileAction = {
    key: string;
    label: string;
    destructive?: boolean;
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
        reorder?: ReorderAction | null;
        moveTargets?: { collection: string; label: string }[];
        disabled?: boolean;
        labels?: Partial<
            Record<'item' | 'empty' | 'actions' | 'remove' | 'moveTo', string>
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
}>();
const tiles = computed(() => props.layout === 'tiles');
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
        !!props.direct &&
        (!props.single || items.value.length === 0),
);
const itemLabel = (item: MediaItem, index: number) =>
    `${text.value.item} ${index + 1}${item.name ? ` (${item.name})` : ''}`;
</script>
<template>
    <section
        class="grid gap-3"
        :aria-label="label"
        :data-media-collection="collection.collection"
    >
        <p
            v-if="items.length === 0 && !(photoTile && canAdd)"
            class="text-muted-foreground text-sm"
            data-media-empty
        >
            {{ text.empty }}
        </p>
        <SortableList
            v-else-if="!single && items.length"
            :items="items"
            :item-label="itemLabel"
            :label="label"
            :reorder="reorder"
            :version="collection.version"
            :disabled="disabled"
            :layout="listLayout"
            :move-buttons="tiles ? !!reorder : undefined"
            :cut-after="cutAfter"
            :cut-label="cutLabel"
            @saved="emit('saved', $event)"
            @conflict="emit('conflict', $event)"
        >
            <template #default="{ item, index }">
                <div
                    v-if="tiles"
                    :class="[
                        'grid min-w-0 gap-2',
                        dimIds.includes(item.id) && 'opacity-50',
                    ]"
                    :data-media-item="item.id"
                    data-media-tile
                    :data-selected="selectedIds.includes(item.id) || undefined"
                    :data-dim="dimIds.includes(item.id) || undefined"
                >
                    <button
                        type="button"
                        :class="[
                            tileAspect === 'square'
                                ? 'aspect-square'
                                : 'aspect-4/3',
                            'border-border bg-muted focus-visible:outline-ring relative w-full overflow-hidden rounded-md border focus-visible:outline-2 focus-visible:outline-offset-2',
                            selectedIds.includes(item.id) &&
                                'ring-primary ring-2',
                        ]"
                        :aria-label="`View ${itemLabel(item, index)}`"
                        :aria-pressed="
                            selectedIds.length
                                ? selectedIds.includes(item.id)
                                : undefined
                        "
                        @click="emit('show', item)"
                    >
                        <img
                            v-if="item.thumb && !item.pending"
                            :src="item.thumb"
                            alt=""
                            loading="lazy"
                            class="size-full object-cover"
                        />
                        <span
                            v-else
                            class="bg-muted block size-full animate-pulse"
                            data-media-pending
                        />
                        <span
                            v-if="badges[item.id]"
                            class="bg-background/90 absolute top-1.5 left-1.5 rounded px-1.5 py-0.5 text-xs font-medium"
                            data-media-badge
                            >{{ badges[item.id] }}</span
                        >
                        <span
                            v-if="selectedIds.includes(item.id)"
                            class="bg-primary text-primary-foreground absolute top-1.5 right-1.5 flex size-5 items-center justify-center rounded-full"
                            data-media-selected
                            ><Check class="size-3.5" aria-hidden="true"
                        /></span>
                    </button>
                    <div class="flex min-w-0 items-center gap-1">
                        <span class="min-w-0 flex-1 truncate text-sm">{{
                            item.name
                        }}</span>
                        <DropdownMenu
                            v-if="!disabled && actionsFor(item).length"
                        >
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
                                    v-for="action in actionsFor(item)"
                                    :key="action.key"
                                    :class="
                                        action.destructive &&
                                        'text-destructive-text focus:text-destructive-text'
                                    "
                                    :data-media-action="action.key"
                                    @select="emit('action', action.key, item)"
                                    >{{ action.label }}</DropdownMenuItem
                                >
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
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
            v-if="canAdd && direct && photoTile"
            :class="tiles ? 'max-w-48' : 'max-w-64'"
            :name="collection.collection"
            :label="items.length ? addLabel : label"
            :accept="accept"
            :max-bytes="maxBytes"
            :multiple="!single"
            :aspect="tileAspect"
            :direct="direct"
            @files="emit('files', $event)"
            @uploaded="emit('uploaded', $event)"
        />
        <BulkDrop
            v-else-if="canAdd && direct"
            :name="collection.collection"
            :label="label"
            :accept="accept"
            :max-bytes="maxBytes"
            :has-files="items.length > 0"
            :multiple="!single"
            :direct="direct"
            compact
            @uploaded="emit('uploaded', $event)"
        />
    </section>
</template>

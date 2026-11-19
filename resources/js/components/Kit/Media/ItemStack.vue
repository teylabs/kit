<script setup lang="ts">
import { ChevronDown, ChevronUp, Layers, Plus, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useSlots, watch } from 'vue';
import SortableList from '@/components/Kit/Data/SortableList.vue';
import type {
    ReorderAction,
    ReorderPersist,
} from '@/components/Kit/Data/sortable';
import Button from '@/components/Kit/Form/Button.vue';
import Modal from '@/components/Kit/Overlay/Modal.vue';
import BulkDrop from '@/components/Kit/Form/BulkDrop.vue';
import { sizeHint } from '@/components/Kit/Form/captureFiles';

export type StackItem = {
    id: string;
    label: string;
    preview?: string | null;
    thumb?: string | null;
    srcset?: string | null;
    meta?: string | null;
};

/*
 * An ordered set of pictures shown as one stack (docs/media.md, "A stack"): the current item on top with up to three bordered, shadowed layers peeking out
 * below (one per item behind it, three from four items), a "k of N" chip on the face, a ring that pulses when an item is added, a "k of N" count
 * and Previous/Next steps, and a footer with the count, Add and Arrange. Arrange opens the fanout: the whole stack in a column (or a grid) on SortableList,
 * with a grip, keys and Move buttons per item, each opened or removed from its row. The order is the server's: with `actions.reorder` a move sends
 * `{ order, version }` and a 409 rolls back and emits `conflict`. Add, open and remove are emitted for the host to run. `index` and `fanoutOpen` are models.
 * The fanout's header is `title` and `description` (or the #fanout-header slot); the list ends in the #after slot. With `actions.add` a section below the list
 * adds: the kit's full BulkDrop when `accept` is given (drop, paste or browse emits `files`), else a secondary Button whose click emits `add`. The footer's controls are the kit's ghost Button, one 32px row.
 */
const props = withDefaults(
    defineProps<{
        items: StackItem[];
        label: string;
        actions?: {
            add?: boolean | null;
            reorder?: ReorderAction | null;
            remove?: boolean | null;
        };
        noun?: [string, string];
        orientation?: 'vertical' | 'grid';
        sizes?: string;
        busy?: boolean;
        version?: string | number | null;
        /** The host's own save for the fanout's order, in place of `actions.reorder`: ok keeps the version, conflict rolls back and emits `conflict`. */
        persist?: ReorderPersist | null;
        /** Fill the container's height: the picture takes the space left above the counter and footer (contained, not cropped), so the stack matches a sibling tile's height. */
        fill?: boolean;
        /** false hides the footer's Arrange… (a host that opens its own arrange view from Add or the picture); the fanout still opens through `fanoutOpen`. */
        arrange?: boolean;
        /** The fanout's title, in place of "Arrange <label>" (e.g. "Listing shots"). */
        title?: string | null;
        /** The fanout's description, in place of the count (e.g. "Comparable 2 · 1996 FREIGHTLINER FLD120"). */
        description?: string | null;
        /** The files the fanout's add cell takes: with it the cell is a drop zone (drag in or browse) that emits `files`; without it a click emits `add`. */
        accept?: string[] | null;
        maxBytes?: number;
        maxFiles?: number;
        /** A strip of up to this many thumbnails beside the face, from two items (true is 2); past that the last slot is "+K", which opens the fanout. */
        thumbnails?: number | boolean;
        /** The face's "k of N" chip: 'auto' hides it when the thumbnail strip or a #footer-label already shows the position; true or false forces it. */
        badge?: 'auto' | boolean;
    }>(),
    {
        actions: () => ({}),
        noun: () => ['item', 'items'],
        orientation: 'vertical',
        sizes: '20rem',
        busy: false,
        version: null,
        persist: null,
        arrange: true,
        title: null,
        description: null,
        accept: null,
        maxBytes: 10485760,
        maxFiles: 20,
        thumbnails: false,
        badge: 'auto',
    },
);
const index = defineModel<number>('index', { default: 0 });
const fanoutOpen = defineModel<boolean>('fanoutOpen', { default: false });
const emit = defineEmits<{
    add: [];
    files: [files: File[]];
    open: [id: string, event: Event];
    remove: [id: string];
    saved: [version: string | number | null, body?: unknown];
    conflict: [order: string[]];
}>();
defineSlots<{
    preview?(props: { item: StackItem; index: number }): unknown;
    badge?(props: { item: StackItem; index: number }): unknown;
    menu?(props: { item: StackItem; index: number }): unknown;
    thumb?(props: { item: StackItem; index: number }): unknown;
    /** The footer's label, in place of "<items> · <count>": e.g. "Listing · 1 of 3". The kit still announces the position. */
    'footer-label'?(props: { index: number; count: number }): unknown;
    /** The fanout's header text in place of `title` and `description` (the dialog keeps `title` as its name). */
    'fanout-header'?(props: { count: number }): unknown;
    /** After the fanout's last item, inside the list and above the add section. */
    after?(props: { count: number }): unknown;
}>();
const count = computed(() => props.items.length);
// A host footer label (e.g. "Listing · 1 of 3") carries the position, so the arrows join the footer and the separate "k / N" row goes; the live announcement stays.
const slots = useSlots();
const hostLabel = computed(() => Boolean(slots['footer-label']));
// An added item pulses a ring on the face for a moment (`data-item-stack-added`), so a stack that grew reads as grown; reduced motion keeps the ring, without the pulse.
const added = ref(false);
let addedTimer: ReturnType<typeof setTimeout> | undefined;
watch(count, (now, before) => {
    if (index.value > now - 1) index.value = Math.max(0, now - 1);
    if (now > before) {
        added.value = true;
        clearTimeout(addedTimer);
        addedTimer = setTimeout(() => (added.value = false), 1600);
    }
});
onBeforeUnmount(() => clearTimeout(addedTimer));
const current = computed(() => props.items[index.value] ?? null);
const nounFor = (n: number) => (n === 1 ? props.noun[0] : props.noun[1]);
const layers = computed(() => Math.min(3, Math.max(0, count.value - 1)));
// Each layer sits 0.5rem lower and 0.5rem narrower a side than the one above it; the top item leaves room for all of them.
const layerClass = (depth: number) => [
    'border-foreground/20 bg-card absolute rounded-md border shadow-sm',
    ['inset-x-2', 'inset-x-4', 'inset-x-6'][depth - 1],
    ['top-2', 'top-4', 'top-6'][depth - 1],
    ['bottom-0', 'bottom-2', 'bottom-4'][layers.value - depth],
    depth === 3 ? 'opacity-70' : depth === 2 ? 'opacity-85' : '',
    ['z-[3]', 'z-[2]', 'z-[1]'][depth - 1],
];
// The thumbnail strip: N slots from two items; with more items than slots the last slot is "+K" for the rest.
const slotCount = computed(() =>
    count.value < 2
        ? 0
        : props.thumbnails === true
          ? 2
          : Math.max(0, Math.floor(Number(props.thumbnails) || 0)),
);
const stripItems = computed(() =>
    props.items.slice(
        0,
        count.value > slotCount.value
            ? Math.max(0, slotCount.value - 1)
            : slotCount.value,
    ),
);
const more = computed(() =>
    slotCount.value ? count.value - stripItems.value.length : 0,
);
const showBadge = computed(
    () =>
        count.value > 1 &&
        (props.badge === 'auto'
            ? !slotCount.value && !hostLabel.value
            : props.badge),
);
function step(by: number) {
    if (!count.value) return;
    index.value = (index.value + by + count.value) % count.value;
}
// "JPEG, PNG": the accepted types by their subtype, for the drop zone's hint.
const typesHint = (types: string[]) =>
    types.map((type) => (type.split('/')[1] ?? type).toUpperCase()).join(', ');
const itemLabel = (item: StackItem, at: number) =>
    `${props.noun[0][0].toUpperCase()}${props.noun[0].slice(1)} ${at + 1} (${item.label})`;
</script>
<template>
    <section
        :class="fill ? 'flex h-full min-h-0 flex-col gap-2' : 'grid gap-2'"
        :aria-label="label"
        data-item-stack
        :data-count="count"
    >
        <!-- With the strip, the face and strip share one inset from the tile's edge (the footer's px-2) and a gap between them. -->
        <div
            v-if="current"
            :class="[
                'flex',
                slotCount ? 'gap-2 p-2' : 'gap-1.5',
                fill && 'min-h-0 flex-1',
            ]"
        >
            <div
                :class="[
                    'relative min-w-0 flex-1',
                    ['', 'pb-2', 'pb-4', 'pb-6'][layers],
                ]"
            >
                <span
                    v-for="depth in [3, 2, 1].filter((d) => d <= layers)"
                    :key="depth"
                    aria-hidden="true"
                    :class="layerClass(depth)"
                    data-item-stack-layer
                />
                <button
                    type="button"
                    :class="[
                        'border-border bg-card focus-visible:outline-ring relative z-10 block w-full overflow-hidden rounded-md border shadow-sm transition-shadow focus-visible:outline-2 focus-visible:outline-offset-2',
                        fill && 'h-full',
                    ]"
                    :aria-label="`Open ${itemLabel(current, index)}`"
                    data-item-stack-top
                    @click="emit('open', current.id, $event)"
                >
                    <slot name="preview" :item="current" :index="index">
                        <img
                            v-if="current.preview ?? current.thumb"
                            :src="(current.preview ?? current.thumb)!"
                            :srcset="current.srcset ?? undefined"
                            :sizes="sizes"
                            alt=""
                            :class="
                                fill
                                    ? 'h-full w-full object-contain'
                                    : 'aspect-4/3 w-full object-cover object-top'
                            "
                        />
                        <span
                            v-else
                            :class="[
                                'bg-muted block w-full animate-pulse',
                                fill ? 'h-full' : 'aspect-4/3',
                            ]"
                            data-media-pending
                        />
                    </slot>
                    <span class="absolute top-1.5 left-1.5"
                        ><slot name="badge" :item="current" :index="index"
                    /></span>
                    <span
                        v-if="showBadge"
                        aria-hidden="true"
                        class="bg-background/90 text-foreground absolute top-1.5 right-1.5 rounded px-1.5 py-0.5 text-xs font-medium tabular-nums shadow-sm"
                        data-item-stack-count
                        >{{ index + 1 }} of {{ count }}</span
                    >
                    <span
                        v-if="added"
                        aria-hidden="true"
                        class="ring-primary pointer-events-none absolute inset-0 z-20 rounded-md ring-2 ring-inset motion-safe:animate-pulse"
                        data-item-stack-added
                    />
                </button>
            </div>
            <!-- The strip: as tall as the face (absolute in a column that stretches with the row), its slots shrinking to fit. -->
            <div v-if="slotCount" class="relative w-12 shrink-0">
                <div
                    role="group"
                    :aria-label="`${label} thumbnails`"
                    class="absolute inset-0 flex flex-col gap-1.5"
                    data-item-stack-thumbnails
                >
                    <button
                        v-for="(item, at) in stripItems"
                        :key="item.id"
                        type="button"
                        :class="[
                            'bg-muted focus-visible:outline-ring block aspect-square min-h-0 w-full shrink overflow-hidden rounded border focus-visible:outline-2 focus-visible:outline-offset-1',
                            at === index
                                ? 'border-primary ring-primary ring-1'
                                : 'border-border',
                        ]"
                        :aria-label="`Show ${itemLabel(item, at)}`"
                        :aria-current="at === index ? 'true' : undefined"
                        :data-item-stack-thumbnail="item.id"
                        @click="index = at"
                    >
                        <img
                            v-if="item.thumb ?? item.preview"
                            :src="(item.thumb ?? item.preview)!"
                            alt=""
                            loading="lazy"
                            class="h-full w-full object-cover"
                        />
                    </button>
                    <button
                        v-if="more"
                        type="button"
                        class="bg-muted text-foreground hover:bg-accent focus-visible:outline-ring border-border flex aspect-square min-h-0 w-full shrink items-center justify-center rounded border text-xs font-medium tabular-nums focus-visible:outline-2 focus-visible:outline-offset-1"
                        :aria-label="`${more} more ${nounFor(more)}: arrange all ${count}`"
                        data-item-stack-more
                        @click="fanoutOpen = true"
                    >
                        +{{ more }}
                    </button>
                </div>
            </div>
        </div>
        <span
            v-if="count > 1 && hostLabel"
            class="sr-only"
            aria-live="polite"
            data-item-stack-announce
            >{{ noun[0] }} {{ index + 1 }} of {{ count }}</span
        >
        <div
            v-if="count > 1 && !hostLabel"
            class="flex items-center justify-between gap-2 px-2 text-sm"
        >
            <span data-item-stack-position aria-live="polite"
                >{{ index + 1 }} / {{ count }}</span
            >
            <span class="flex gap-1">
                <button
                    type="button"
                    class="hover:bg-accent inline-flex size-8 items-center justify-center rounded-md"
                    :aria-label="`Previous ${noun[0]}`"
                    data-item-stack-previous
                    @click="step(-1)"
                >
                    <ChevronUp class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="hover:bg-accent inline-flex size-8 items-center justify-center rounded-md"
                    :aria-label="`Next ${noun[0]}`"
                    data-item-stack-next
                    @click="step(1)"
                >
                    <ChevronDown class="size-4" aria-hidden="true" />
                </button>
            </span>
        </div>
        <footer
            class="text-muted-foreground @container flex min-h-8 items-center gap-0.5 px-2 text-sm"
            data-item-stack-footer
        >
            <span
                class="min-w-[min(6rem,100%)] flex-1 truncate leading-8"
                data-item-stack-footer-label
                ><slot name="footer-label" :index="index" :count="count"
                    >{{ noun[1] }} · {{ count }}</slot
                ></span
            >
            <span v-if="count > 1 && hostLabel" class="flex shrink-0">
                <Button
                    variant="ghost"
                    size="icon"
                    type="button"
                    class="text-foreground h-8 w-6"
                    :aria-label="`Previous ${noun[0]}`"
                    data-item-stack-previous
                    @click="step(-1)"
                >
                    <ChevronUp class="size-4" aria-hidden="true" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    type="button"
                    class="text-foreground h-8 w-6"
                    :aria-label="`Next ${noun[0]}`"
                    data-item-stack-next
                    @click="step(1)"
                >
                    <ChevronDown class="size-4" aria-hidden="true" />
                </Button>
            </span>
            <Button
                v-if="actions.add"
                variant="ghost"
                size="sm"
                type="button"
                class="text-foreground h-8 min-w-7 gap-1 px-1.5 has-[>svg]:px-1.5"
                :disabled="busy"
                data-item-stack-add
                @click="emit('add')"
            >
                <Plus class="size-4" aria-hidden="true" /><span
                    class="sr-only @2xs:not-sr-only"
                    >Add</span
                >
            </Button>
            <Button
                v-if="count > 1 && arrange"
                variant="ghost"
                size="sm"
                type="button"
                class="text-foreground h-8 min-w-8 gap-1 px-2 has-[>svg]:px-2"
                data-item-stack-arrange
                @click="fanoutOpen = true"
            >
                <Layers class="size-4" aria-hidden="true" /><span
                    class="sr-only @2xs:not-sr-only"
                    >Arrange…</span
                >
            </Button>
            <span v-if="current" class="flex shrink-0 items-center"
                ><slot name="menu" :item="current" :index="index"
            /></span>
        </footer>
        <Modal
            v-model:open="fanoutOpen"
            :title="title ?? `Arrange ${label}`"
            :description="
                slots['fanout-header']
                    ? undefined
                    : (description ?? `${count} ${nounFor(count)}`)
            "
            size="lg"
        >
            <div
                v-if="slots['fanout-header']"
                class="text-muted-foreground -mt-2 mb-3 text-sm"
                data-item-stack-fanout-header
            >
                <slot name="fanout-header" :count="count" />
            </div>
            <div data-item-stack-fanout :data-orientation="orientation">
                <SortableList
                    :items="items"
                    :item-label="itemLabel"
                    :label="label"
                    :reorder="actions.reorder ?? null"
                    :persist="persist"
                    :settle="400"
                    :version="version"
                    :disabled="busy || !(actions.reorder || persist)"
                    :layout="orientation === 'grid' ? 'grid' : 'list'"
                    @saved="(version, body) => emit('saved', version, body)"
                    @conflict="emit('conflict', $event)"
                >
                    <template #default="{ item, index: at }">
                        <div
                            class="flex min-w-0 flex-1 items-center gap-2"
                            :data-item-stack-item="item.id"
                        >
                            <button
                                type="button"
                                class="focus-visible:outline-ring flex min-w-0 flex-1 items-center gap-2 rounded-md text-left focus-visible:outline-2"
                                :aria-label="`Open ${itemLabel(item, at)}`"
                                @click="emit('open', item.id, $event)"
                            >
                                <slot name="thumb" :item="item" :index="at">
                                    <img
                                        v-if="item.thumb ?? item.preview"
                                        :src="(item.thumb ?? item.preview)!"
                                        alt=""
                                        loading="lazy"
                                        class="border-border size-12 shrink-0 rounded border object-cover"
                                    />
                                    <span
                                        v-else
                                        class="bg-muted size-12 shrink-0 rounded"
                                        data-media-pending
                                    />
                                </slot>
                                <span class="min-w-0 truncate text-sm">{{
                                    item.label
                                }}</span>
                                <span
                                    v-if="item.meta"
                                    class="text-muted-foreground text-xs"
                                    >{{ item.meta }}</span
                                >
                            </button>
                            <button
                                v-if="actions.remove"
                                type="button"
                                class="hover:bg-accent text-destructive-text inline-flex size-8 items-center justify-center rounded-md"
                                :aria-label="`Remove ${itemLabel(item, at)}`"
                                data-item-stack-remove
                                @click="emit('remove', item.id)"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                            </button>
                        </div>
                    </template>
                    <template v-if="slots.after" #after>
                        <slot name="after" :count="count" />
                    </template>
                </SortableList>
                <div v-if="actions.add" class="mt-4" data-item-stack-add-cell>
                    <BulkDrop
                        v-if="accept"
                        name="item-stack"
                        :label="label"
                        :accept="accept"
                        :max-bytes="maxBytes"
                        :has-files="false"
                        :lead="`Drop ${noun[1]} here, or`"
                        :hint="`${typesHint(accept)} · ${sizeHint(maxBytes)}`"
                        multiple
                        :max-files="maxFiles"
                        hand-off
                        :disabled="busy"
                        handle-drops
                        data-item-stack-drop
                        @files="emit('files', $event)"
                    />
                    <Button
                        v-else
                        variant="secondary"
                        type="button"
                        :disabled="busy"
                        data-item-stack-add
                        @click="emit('add')"
                    >
                        <Plus class="size-4" aria-hidden="true" />Add
                        {{ noun[0] }}
                    </Button>
                </div>
            </div>
        </Modal>
    </section>
</template>

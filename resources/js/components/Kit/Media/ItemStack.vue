<script setup lang="ts">
import { ChevronDown, ChevronUp, Layers, Plus, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
import SortableList from '@/components/Kit/Data/SortableList.vue';
import type {
    ReorderAction,
    ReorderPersist,
} from '@/components/Kit/Data/sortable';
import Modal from '@/components/Kit/Overlay/Modal.vue';

export type StackItem = {
    id: string;
    label: string;
    preview?: string | null;
    thumb?: string | null;
    srcset?: string | null;
    meta?: string | null;
};

/*
 * An ordered set of pictures shown as one stack (docs/media.md, "A stack"): the current item on top with plain layers peeking out below, a "k of N" count
 * and Previous/Next steps, and a footer with the count, Add and Arrange. Arrange opens the fanout: the whole stack in a column (or a grid) on SortableList,
 * with a grip, keys and Move buttons per item, each opened or removed from its row. The order is the server's: with `actions.reorder` a move sends
 * `{ order, version }` and a 409 rolls back and emits `conflict`. Add, open and remove are emitted for the host to run. `index` and `fanoutOpen` are models.
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
    }>(),
    {
        actions: () => ({}),
        noun: () => ['item', 'items'],
        orientation: 'vertical',
        sizes: '20rem',
        busy: false,
        version: null,
        persist: null,
    },
);
const index = defineModel<number>('index', { default: 0 });
const fanoutOpen = defineModel<boolean>('fanoutOpen', { default: false });
const emit = defineEmits<{
    add: [];
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
}>();
const count = computed(() => props.items.length);
watch(count, (now) => {
    if (index.value > now - 1) index.value = Math.max(0, now - 1);
});
const current = computed(() => props.items[index.value] ?? null);
const nounFor = (n: number) => (n === 1 ? props.noun[0] : props.noun[1]);
const layers = computed(() => Math.min(2, Math.max(0, count.value - 1)));
function step(by: number) {
    if (!count.value) return;
    index.value = (index.value + by + count.value) % count.value;
}
const itemLabel = (item: StackItem, at: number) =>
    `${props.noun[0][0].toUpperCase()}${props.noun[0].slice(1)} ${at + 1} (${item.label})`;
</script>
<template>
    <section
        class="grid gap-2"
        :aria-label="label"
        data-item-stack
        :data-count="count"
    >
        <div
            v-if="current"
            :class="[
                'relative',
                layers === 2 ? 'pb-3' : layers === 1 ? 'pb-1.5' : '',
            ]"
        >
            <span
                v-if="layers === 2"
                aria-hidden="true"
                class="border-border bg-muted absolute inset-x-4 top-3 bottom-0 rounded-md border"
                data-item-stack-layer
            />
            <span
                v-if="layers >= 1"
                aria-hidden="true"
                :class="[
                    'border-border bg-muted absolute inset-x-2 top-1.5 rounded-md border',
                    layers === 2 ? 'bottom-1.5' : 'bottom-0',
                ]"
                data-item-stack-layer
            />
            <button
                type="button"
                class="border-border bg-card focus-visible:outline-ring relative z-10 block w-full overflow-hidden rounded-md border focus-visible:outline-2 focus-visible:outline-offset-2"
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
                        class="aspect-4/3 w-full object-cover object-top"
                    />
                    <span
                        v-else
                        class="bg-muted block aspect-4/3 w-full animate-pulse"
                        data-media-pending
                    />
                </slot>
                <span class="absolute top-1.5 left-1.5"
                    ><slot name="badge" :item="current" :index="index"
                /></span>
            </button>
        </div>
        <div
            v-if="count > 1"
            class="flex items-center justify-between gap-2 text-sm"
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
            class="text-muted-foreground flex items-center gap-2 text-sm"
            data-item-stack-footer
        >
            <span class="flex-1">{{ noun[1] }} · {{ count }}</span>
            <button
                v-if="actions.add"
                type="button"
                class="hover:bg-accent text-foreground inline-flex items-center gap-1 rounded-md px-2 py-1"
                :disabled="busy"
                data-item-stack-add
                @click="emit('add')"
            >
                <Plus class="size-4" aria-hidden="true" />Add
            </button>
            <button
                v-if="count > 1"
                type="button"
                class="hover:bg-accent text-foreground inline-flex items-center gap-1 rounded-md px-2 py-1"
                data-item-stack-arrange
                @click="fanoutOpen = true"
            >
                <Layers class="size-4" aria-hidden="true" />Arrange…
            </button>
            <slot v-if="current" name="menu" :item="current" :index="index" />
        </footer>
        <Modal
            v-model:open="fanoutOpen"
            :title="`Arrange ${label}`"
            :description="`${count} ${nounFor(count)}`"
            size="lg"
        >
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
                </SortableList>
                <button
                    v-if="actions.add"
                    type="button"
                    class="hover:bg-accent mt-3 inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm"
                    data-item-stack-add
                    @click="emit('add')"
                >
                    <Plus class="size-4" aria-hidden="true" />Add
                    {{ noun[0] }}
                </button>
            </div>
        </Modal>
    </section>
</template>

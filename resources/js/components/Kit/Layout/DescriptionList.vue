<script setup lang="ts">
import { Check, CircleCheck, Copy } from '@lucide/vue';
import { ref } from 'vue';

export type DescriptionItem = {
    term: string;
    description: string | number | null;
    key?: string;
    copy?: boolean;
    highlight?: boolean;
};

/*
 * Term and description pairs (a record's details, a connection's metadata) as a real <dl>: each pair a <div> holding one <dt> and one <dd>. From `items`, or the
 * default slot for pairs the host writes itself (keep its children <div><dt/><dd/></div>); the `description` slot renders one item's value. `inline` puts the term
 * beside its description from `sm` up and stacks them on a phone; `stacked` always stacks. An empty value shows `empty` (an em dash). `copy` on an item, or
 * `copyable` on the list, adds a button that copies the value; the result is spoken once through a polite status beside it. `grid` puts the term over its
 * value in `columns` (1 to 3, from `sm` up); `compact` runs the pairs in one wrapping row, term beside value (a dense card). `highlight` on an item marks it
 * as matching what the reader compares it with: the success tone's tint, a check and a spoken "(matches)", never the colour alone. Utilities and tokens only.
 */
const props = withDefaults(
    defineProps<{
        items?: DescriptionItem[];
        layout?: 'inline' | 'stacked' | 'grid' | 'compact';
        columns?: 1 | 2 | 3;
        copyable?: boolean;
        empty?: string;
    }>(),
    {
        items: () => [],
        layout: 'inline',
        columns: 2,
        copyable: false,
        empty: '—',
    },
);
// Whole class strings, never built from a prop (Tailwind finds classes by scanning the source).
const LIST: Record<string, string> = {
    inline: 'grid gap-y-3 sm:gap-y-2',
    stacked: 'grid gap-y-3',
    compact: 'flex flex-wrap gap-x-4 gap-y-1',
};
const GRID_COLUMNS = {
    1: 'grid gap-x-6 gap-y-3',
    2: 'grid gap-x-6 gap-y-3 sm:grid-cols-2',
    3: 'grid gap-x-6 gap-y-3 sm:grid-cols-3',
} as const;
const ITEM: Record<string, string> = {
    inline: 'grid min-w-0 gap-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] sm:gap-4',
    stacked: 'grid min-w-0 gap-1',
    grid: 'grid min-w-0 gap-0.5',
    compact: 'flex min-w-0 flex-wrap items-baseline gap-x-1.5',
};
const copied = ref<string | null>(null);
const failed = ref<string | null>(null);
const keyOf = (item: DescriptionItem, index: number) =>
    item.key ?? `${index}:${item.term}`;
const blank = (item: DescriptionItem) =>
    item.description === null || item.description === '';
const canCopy = (item: DescriptionItem) =>
    !blank(item) && (item.copy ?? props.copyable);
async function copy(item: DescriptionItem, index: number): Promise<void> {
    const key = keyOf(item, index);
    try {
        await navigator.clipboard.writeText(String(item.description));
        copied.value = key;
        failed.value = null;
    } catch {
        copied.value = null;
        failed.value = key;
    }
}
</script>
<template>
    <dl
        :class="[
            'min-w-0 text-sm',
            layout === 'grid' ? GRID_COLUMNS[columns] : LIST[layout],
        ]"
        :data-layout="layout"
        :data-columns="layout === 'grid' ? columns : undefined"
        data-description-list
    >
        <slot>
            <div
                v-for="(item, index) in items"
                :key="keyOf(item, index)"
                :class="[
                    ITEM[layout],
                    item.highlight &&
                        '-mx-1.5 rounded-md bg-(--kit-tone-success-bg) px-1.5 py-0.5',
                ]"
                :data-highlight="item.highlight || undefined"
                data-description-item
            >
                <dt class="text-muted-foreground min-w-0 font-normal">
                    {{ item.term }}
                </dt>
                <dd class="text-foreground m-0 flex min-w-0 items-start gap-2">
                    <span class="min-w-0 break-words"
                        ><slot name="description" :item="item">{{
                            blank(item) ? empty : item.description
                        }}</slot></span
                    >
                    <span
                        v-if="item.highlight"
                        class="inline-flex shrink-0 items-center"
                        data-description-match
                        ><CircleCheck
                            class="size-3.5 text-(--kit-tone-success-fg)"
                            aria-hidden="true"
                        /><span class="sr-only">(matches)</span></span
                    >
                    <template v-if="canCopy(item)">
                        <button
                            type="button"
                            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-ring -my-1 inline-flex size-7 shrink-0 items-center justify-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2"
                            :aria-label="`Copy ${item.term}`"
                            data-description-copy
                            @click="copy(item, index)"
                        >
                            <Check
                                v-if="copied === keyOf(item, index)"
                                class="size-4"
                                aria-hidden="true"
                            />
                            <Copy v-else class="size-4" aria-hidden="true" />
                        </button>
                        <span role="status" class="sr-only">{{
                            copied === keyOf(item, index)
                                ? `${item.term} copied`
                                : failed === keyOf(item, index)
                                  ? `Could not copy ${item.term}`
                                  : ''
                        }}</span>
                    </template>
                </dd>
            </div>
        </slot>
    </dl>
</template>

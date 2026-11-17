<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, useId, watch } from 'vue';
import Badge from '@/components/Kit/Feedback/Badge.vue';
import Button from '@/components/Kit/Form/Button.vue';
import Input from '@/components/Kit/Form/Input.vue';
import InputCheckbox from '@/components/Kit/Form/InputCheckbox.vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';
import Popover from '@/components/Kit/Overlay/Popover.vue';
import {
    activeCount,
    effective,
    filterKind,
    joinMulti,
    patchQuery,
    readValues,
    splitMulti,
    type FilterDescriptor,
    type FilterValues,
} from './filters';

/*
 * A page toolbar's filters as one control (filter-popover.md): a "Filters" button with an info badge counting the filters off their default, opening a
 * popover of the same declarations DataTable reads (TableQuery's `choices`, filters.ts). A select applies at once; a multi filter's ticks edit a draft that
 * applies in one visit when the popover closes, however it closes (Escape, an outside press, Tab out). A search filter narrows the options of the multi it
 * `narrows`, in the browser. The URL is the state: values are read from it and written with a replace visit that keeps every other param (`scope` nests them
 * as TableQuery's `filter[key]`). The popover is the only scroller: the selects and search are pinned at its top, each group's heading sticks under them.
 */
const props = withDefaults(
    defineProps<{
        filters: FilterDescriptor[];
        label?: string;
        resettable?: boolean;
        resetLabel?: string;
        scope?: string | null;
    }>(),
    { label: 'Filters', resettable: false, resetLabel: 'Reset', scope: null },
);

const uid = useId();
const open = ref(false);
const search = () =>
    typeof window === 'undefined' ? '' : window.location.search;
const values = ref<FilterValues>(
    readValues(props.filters, search(), props.scope),
);
const reread = () =>
    (values.value = readValues(props.filters, search(), props.scope));
const stopSuccess = router.on('success', reread);
onBeforeUnmount(() => stopSuccess?.());

const shown = computed(() =>
    props.filters.filter(
        (filter) =>
            filterKind(filter) !== 'select' || filter.options.length > 1,
    ),
);
const selects = computed(() =>
    shown.value.filter((filter) => filterKind(filter) === 'select'),
);
const searches = computed(() =>
    shown.value.filter((filter) => filterKind(filter) === 'search'),
);
const multis = computed(() =>
    shown.value.filter((filter) => filterKind(filter) === 'multi'),
);
const count = computed(() => activeCount(props.filters, values.value));
const name = computed(() =>
    count.value ? `${props.label}, ${count.value} active` : props.label,
);

// The draft: each multi filter's ticks while the popover is open. Search text is local and never sent.
const draft = reactive<Record<string, Set<string>>>({});
const text = reactive<Record<string, string>>({});
const fill = () => {
    for (const filter of multis.value)
        draft[filter.key] = new Set(
            splitMulti(effective(filter, values.value[filter.key])),
        );
};
fill();

function visit(patch: FilterValues) {
    values.value = { ...values.value, ...patch };
    router.get(
        window.location.pathname,
        patchQuery(props.filters, search(), patch, props.scope) as never,
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function applyDraft() {
    const patch: FilterValues = {};
    for (const filter of multis.value) {
        const next = joinMulti(filter, draft[filter.key] ?? []);
        const now = joinMulti(
            filter,
            splitMulti(effective(filter, values.value[filter.key])),
        );
        if (next !== now) patch[filter.key] = next;
    }
    if (Object.keys(patch).length) visit(patch);
}

watch(open, (now) => {
    if (now) {
        reread();
        fill();
        for (const filter of searches.value) text[filter.key] = '';
    } else applyDraft();
});

function choose(filter: FilterDescriptor, value: unknown) {
    const next =
        value === null || value === undefined || value === ''
            ? null
            : String(value);
    if (next === effective(filter, values.value[filter.key])) return;
    visit({ [filter.key]: next });
}

function tick(filter: FilterDescriptor, value: string, on: boolean) {
    const set = new Set(draft[filter.key]);
    if (on) set.add(value);
    else set.delete(value);
    draft[filter.key] = set;
}

function reset() {
    const patch: FilterValues = {};
    for (const filter of props.filters)
        if (filterKind(filter) !== 'search') patch[filter.key] = null;
    for (const filter of multis.value)
        draft[filter.key] = new Set(splitMulti(filter.default));
    for (const filter of searches.value) text[filter.key] = '';
    visit(patch);
}

// A multi filter's options, narrowed by any search that narrows it, in their groups (in first-seen order).
function groups(filter: FilterDescriptor) {
    const needles = searches.value
        .filter((s) => s.narrows === filter.key)
        .map((s) => (text[s.key] ?? '').trim().toLowerCase())
        .filter(Boolean);
    const out: {
        heading: string | null;
        options: FilterDescriptor['options'];
    }[] = [];
    for (const option of filter.options) {
        const hay = `${option.label} ${option.group ?? ''}`.toLowerCase();
        if (needles.some((needle) => !hay.includes(needle))) continue;
        const heading = option.group ?? null;
        let group = out.find((g) => g.heading === heading);
        if (!group) out.push((group = { heading, options: [] }));
        group.options.push(option);
    }
    return out;
}

// The pin's height, for the group headings to stick under it.
const pin = ref<HTMLElement | null>(null);
const pinHeight = ref(0);
let observer: ResizeObserver | undefined;
watch(pin, (el, old) => {
    if (old) observer?.unobserve(old);
    if (!el) return;
    observer ??= new ResizeObserver(() => {
        pinHeight.value = pin.value?.offsetHeight ?? 0;
    });
    observer.observe(el);
    pinHeight.value = el.offsetHeight;
});
onBeforeUnmount(() => observer?.disconnect());

const optionId = (filter: FilterDescriptor, value: string) =>
    `${uid}-${filter.key}-${value}`;
const allLabel = (filter: FilterDescriptor) =>
    `All ${filter.label.toLowerCase()}`;
const hasPin = computed(
    () =>
        selects.value.length > 0 ||
        searches.value.length > 0 ||
        props.resettable,
);
</script>
<template>
    <Popover
        v-model:open="open"
        :label="label"
        :close-button="false"
        :sheet="false"
        stay-on-visit
        align="end"
        class="w-[min(22rem,calc(100vw-2rem))] max-w-[calc(100vw-2rem)] pt-0"
        :style="{ '--filter-pin': `${pinHeight}px` }"
        data-filter-popover-panel
    >
        <template #trigger>
            <Button
                variant="secondary"
                size="sm"
                chevron
                :aria-label="name"
                data-filter-popover
            >
                <SlidersHorizontal class="size-4" aria-hidden="true" />
                {{ label }}
                <Badge
                    v-if="count > 0"
                    tone="info"
                    aria-hidden="true"
                    data-filter-count
                    >{{ count }}</Badge
                >
            </Button>
        </template>
        <div
            v-if="hasPin"
            ref="pin"
            class="bg-popover sticky top-0 z-20 -mx-4 grid gap-3 px-4 pt-4 pb-3"
            data-filter-pin
        >
            <div
                v-for="filter in selects"
                :key="filter.key"
                class="grid gap-1"
                :data-filter="filter.key"
            >
                <label
                    :for="`${uid}-${filter.key}`"
                    class="text-muted-foreground text-xs font-medium"
                    >{{ filter.label }}</label
                >
                <InputSelect
                    :id="`${uid}-${filter.key}`"
                    :model-value="effective(filter, values[filter.key])"
                    :options="filter.options"
                    :placeholder="filter.label"
                    :clearable="!filter.default"
                    :searchable="false"
                    @update:model-value="(value) => choose(filter, value)"
                />
            </div>
            <Input
                v-for="filter in searches"
                :key="filter.key"
                v-model="text[filter.key]"
                type="search"
                :placeholder="`${filter.label}…`"
                :aria-label="filter.label"
                :data-filter="filter.key"
            />
            <div v-if="resettable" class="flex justify-end">
                <Button
                    variant="link"
                    size="sm"
                    class="h-auto px-0"
                    data-filter-reset
                    @click="reset"
                    >{{ resetLabel }}</Button
                >
            </div>
        </div>
        <fieldset
            v-for="filter in multis"
            :key="filter.key"
            class="min-w-0 pt-3"
            :data-filter="filter.key"
        >
            <legend class="sr-only">{{ filter.label }}</legend>
            <InputCheckbox
                :id="optionId(filter, '')"
                :model-value="(draft[filter.key]?.size ?? 0) === 0"
                class="py-1"
                data-filter-all
                @update:model-value="
                    (on?: boolean) => on && (draft[filter.key] = new Set())
                "
                >{{ allLabel(filter) }}</InputCheckbox
            >
            <div
                v-for="(group, index) in groups(filter)"
                :key="group.heading ?? ''"
                role="group"
                :aria-labelledby="
                    group.heading
                        ? `${optionId(filter, '')}-g${index}`
                        : undefined
                "
                data-filter-group
            >
                <p
                    v-if="group.heading"
                    :id="`${optionId(filter, '')}-g${index}`"
                    class="bg-popover text-muted-foreground sticky top-(--filter-pin) z-10 -mx-4 px-4 py-1.5 text-xs font-semibold"
                    data-filter-heading
                >
                    {{ group.heading }}
                </p>
                <InputCheckbox
                    v-for="option in group.options"
                    :id="optionId(filter, option.value)"
                    :key="option.value"
                    :model-value="draft[filter.key]?.has(option.value) ?? false"
                    class="py-1"
                    :data-filter-option="option.value"
                    @update:model-value="
                        (on?: boolean) => tick(filter, option.value, !!on)
                    "
                    >{{ option.label }}</InputCheckbox
                >
            </div>
        </fieldset>
    </Popover>
</template>

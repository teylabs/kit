<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, inject, provide, ref, useId, watch } from 'vue';
import InertiaAction from '@/components/Kit/Action/InertiaAction.vue';
import SeeAllLink from '@/components/Kit/Action/SeeAllLink.vue';
import { normalAction } from '@/components/Kit/Action/singleAction';
import { seeAllPlacement } from '@/components/Kit/Action/seeAll';
import Button from '@/components/Kit/Form/Button.vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';
import { cn } from '@/components/Kit/utils';
import {
    collectionFrameKey,
    type CollectionSectionData,
} from './collectionSection';

/*
 * A section that shows a collection (docs/collection-section.md): a list, a grid, a feed or table rows, in the default slot. The server's `section`
 * (CollectionSectionData, or a dashboard widget's frame) decides everything around them:
 * - the title, a heading at `level` (3 by default) that links to `seeAll`'s URL unless it says `linkTitle: false` (heading-coloured, underlined on hover);
 *   `description` under it;
 * - on the header's right, the Sort by menu (`sortBy`, while ready), the `#actions` slot, the descriptor `actions` (a GET action with the see-all's URL is
 *   left out, so the link is drawn once) and a header see-all. A plain GET action is a compact link; one with a confirmation, `disabled`, `newTab` or another
 *   method keeps its meaning through InertiaAction (it asks first, shows its reason, and is a button);
 * - below the header, the `segments` (tabs such as All / New / In progress: links to the same page, the current one `aria-current`, named "New (4)" with a
 *   count) and the `#filters` slot;
 * - the body: the items, or the empty state (`empty`, or the `#empty` slot; an empty section never shows its items, even with neither), or the error with
 *   Retry. With `maxHeight` the body scrolls (a focusable region
 *   named "<title> items"), and the footer stays in view under it;
 * - the footer: the `#footer` slot and, at the bottom right, the see-all ("All cases →"). An error section has no see-all, and its title is plain text.
 * It draws no card: on its own it is a bare section, for a grid or list whose items carry their own look (a grid of note cards), with the see-all under the
 * items at the right. CollectionCard puts it in a kit Card, with the same descriptor, by handing it a frame: the classes for its parts' padding and rules.
 * Choosing an order visits `sortBy.url` with `{ sort }` (its method; a partial visit for `sortBy.reload`), puts the old order back when refused, and remounts
 * the items on the new one. `headless` drops the title bar (the section keeps the title as its accessible name). `minHeight` holds the ready body's height
 * while its content loads (a widget's `loadingHeight`).
 */
const props = withDefaults(
    defineProps<{
        section: CollectionSectionData;
        level?: 2 | 3 | 4 | 5 | 6;
        headless?: boolean;
        minHeight?: string | null;
    }>(),
    { level: 3, headless: false, minHeight: null },
);
// The frame a CollectionCard gives this section's parts; a section among its items starts with none.
const frame = inject(collectionFrameKey, null);
provide(collectionFrameKey, null);
const id = useId();
const headingId = `${id}-title`;
const sortId = `${id}-sort`;
const heading = computed(() => `h${props.level}`);
const state = computed(() => props.section.state ?? 'ready');
const seeAll = computed(() =>
    state.value === 'error' ? null : (props.section.seeAll ?? null),
);
const placement = computed(() => seeAllPlacement(seeAll.value));
const titleSeeAll = computed(() =>
    seeAll.value && seeAll.value.linkTitle !== false ? seeAll.value : null,
);
const actions = computed(() =>
    state.value === 'error'
        ? []
        : (props.section.actions ?? [])
              .filter(
                  (action) =>
                      !(
                          props.section.seeAll &&
                          (action.method ?? 'get') === 'get' &&
                          action.url === props.section.seeAll.url
                      ),
              )
              .map((action) => {
                  const normal = normalAction({
                      ...action,
                      method: action.method ?? 'get',
                  });
                  const plain =
                      normal.method === 'get' &&
                      !normal.confirm &&
                      !normal.disabled &&
                      !normal.newTab;
                  return { action, plain };
              }),
);
const segments = computed(() =>
    state.value === 'error' ? [] : (props.section.segments ?? []),
);
const scrolls = computed(
    () => state.value !== 'error' && Boolean(props.section.maxHeight),
);
const minHeight = computed(() =>
    state.value === 'ready' ? props.minHeight || null : null,
);
const retry = () => router.reload();

// Sort by: the shown order changes only once the server takes the choice; a refusal puts the select back.
const sort = computed(() =>
    state.value === 'ready' ? (props.section.sortBy ?? null) : null,
);
const sortOptions = computed(() =>
    (sort.value?.options ?? []).map((option) => ({
        value: option.key,
        label: option.label,
    })),
);
const current = ref(props.section.sortBy?.current ?? null);
const shown = ref(current.value);
const saving = ref(false);
watch(
    () => props.section.sortBy?.current,
    (value) => {
        current.value = value ?? null;
        shown.value = value ?? null;
    },
);
function choose(value: unknown): void {
    const next = typeof value === 'string' ? value : null;
    if (!sort.value || next === null || next === shown.value || saving.value)
        return;
    const previous = shown.value;
    current.value = next;
    saving.value = true;
    router.visit(sort.value.url, {
        method: sort.value.method as 'get',
        data: { sort: next },
        only: sort.value.reload,
        // A list starts again from its first page.
        reset: sort.value.reload,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            shown.value = next;
        },
        onError: () => {
            current.value = previous;
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>
<template>
    <section
        :aria-labelledby="headless ? undefined : headingId"
        :aria-label="headless ? section.title : undefined"
        :class="cn('flex min-w-0 flex-col gap-4', frame?.root)"
        data-collection-section
        :data-state="state"
    >
        <header
            v-if="!headless"
            :class="
                cn(
                    'flex flex-wrap items-center justify-between gap-x-3 gap-y-2',
                    frame?.header,
                )
            "
            data-collection-header
        >
            <div class="grid min-w-0 gap-0.5">
                <component
                    :is="heading"
                    :id="headingId"
                    class="truncate text-sm font-semibold"
                    ><SeeAllLink
                        v-if="titleSeeAll"
                        :see-all="titleSeeAll"
                        variant="title"
                        >{{ section.title }}</SeeAllLink
                    ><template v-else>{{ section.title }}</template></component
                >
                <p
                    v-if="section.description"
                    class="text-muted-foreground text-xs"
                    data-collection-description
                >
                    {{ section.description }}
                </p>
            </div>
            <div
                v-if="
                    sort ||
                    $slots.actions ||
                    actions.length ||
                    placement === 'header'
                "
                class="flex shrink-0 flex-wrap items-center justify-end gap-2"
            >
                <div
                    v-if="sort"
                    class="flex items-center gap-2"
                    data-collection-sort
                >
                    <label
                        :for="sortId"
                        class="text-muted-foreground text-xs font-medium whitespace-nowrap"
                        >{{ sort.label }}</label
                    >
                    <div class="w-40">
                        <InputSelect
                            :id="sortId"
                            :model-value="current"
                            :options="sortOptions"
                            :searchable="false"
                            :clearable="false"
                            :disabled="saving"
                            @update:model-value="choose"
                        />
                    </div>
                </div>
                <slot name="actions" />
                <template
                    v-for="{ action, plain } in actions"
                    :key="action.key ?? action.url"
                >
                    <Link
                        v-if="plain"
                        :href="action.url"
                        class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring rounded-md px-2 py-1 text-xs font-medium focus-visible:ring-2 focus-visible:outline-none"
                        >{{ action.label }}</Link
                    >
                    <InertiaAction
                        v-else
                        :action="action"
                        size="sm"
                        variant="ghost"
                    />
                </template>
                <SeeAllLink
                    v-if="seeAll && placement === 'header'"
                    :see-all="seeAll"
                />
            </div>
        </header>
        <div
            v-if="segments.length || $slots.filters"
            :class="
                cn(
                    'flex flex-wrap items-center justify-between gap-2',
                    frame?.filters,
                )
            "
            data-collection-filters
        >
            <nav
                v-if="segments.length"
                :aria-label="`${section.title} views`"
                class="flex flex-wrap gap-1.5"
                data-collection-segments
            >
                <Link
                    v-for="segment in segments"
                    :key="segment.key"
                    :href="segment.url"
                    :aria-current="segment.current ? 'page' : undefined"
                    preserve-scroll
                    class="border-border text-muted-foreground hover:text-foreground focus-visible:ring-ring aria-[current]:bg-foreground aria-[current]:text-background inline-flex min-h-7 items-center gap-1.5 rounded-full border px-3 text-xs font-medium focus-visible:ring-2 focus-visible:outline-none aria-[current]:border-transparent"
                    :aria-label="
                        segment.count != null
                            ? `${segment.label} (${segment.count})`
                            : undefined
                    "
                    :data-segment="segment.key"
                    >{{ segment.label
                    }}<span
                        v-if="segment.count != null"
                        class="tabular-nums"
                        data-segment-count
                        >{{ segment.count }}</span
                    ></Link
                >
            </nav>
            <slot name="filters" />
        </div>
        <div
            :class="
                cn(
                    'min-w-0',
                    minHeight
                        ? 'min-h-(--kit-collection-min-height)'
                        : 'min-h-0',
                    scrolls &&
                        'focus-visible:ring-ring max-h-(--kit-collection-max-height) overflow-y-auto overscroll-contain focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset',
                    frame?.body,
                )
            "
            :style="{
                '--kit-collection-max-height': scrolls
                    ? (section.maxHeight ?? undefined)
                    : undefined,
                '--kit-collection-min-height': minHeight ?? undefined,
            }"
            :role="scrolls ? 'region' : undefined"
            :tabindex="scrolls ? 0 : undefined"
            :aria-label="scrolls ? `${section.title} items` : undefined"
            data-collection-body
            :data-collection-max-height="
                (scrolls && section.maxHeight) || undefined
            "
            :data-collection-min-height="minHeight || undefined"
        >
            <div
                v-if="state === 'error'"
                role="alert"
                class="grid justify-items-start gap-2"
                data-collection-error
            >
                <p class="text-muted-foreground text-sm">
                    {{ section.error?.message ?? "This couldn't load." }}
                </p>
                <Button type="button" variant="secondary" @click="retry">{{
                    section.error?.retry?.label ?? 'Retry'
                }}</Button>
            </div>
            <div
                v-else-if="state === 'empty'"
                class="grid justify-items-start gap-1"
                data-collection-empty
                :data-variant="section.empty?.variant"
            >
                <slot name="empty" :empty="section.empty"
                    ><template v-if="section.empty">
                        <p class="font-medium">{{ section.empty.title }}</p>
                        <p class="text-muted-foreground text-sm">
                            {{ section.empty.description }}
                        </p>
                        <Link
                            v-if="section.empty.action"
                            :href="section.empty.action.url"
                            class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring mt-2 inline-flex min-h-11 items-center rounded-md px-4 text-sm font-medium focus-visible:ring-2 focus-visible:outline-none"
                            >{{ section.empty.action.label }}</Link
                        >
                    </template></slot
                >
            </div>
            <div v-else :key="shown ?? ''" class="contents"><slot /></div>
        </div>
        <footer
            v-if="(seeAll && placement === 'footer') || $slots.footer"
            :class="
                cn(
                    'flex flex-wrap items-center justify-end gap-3',
                    frame?.footer,
                )
            "
            data-collection-footer
        >
            <div
                v-if="$slots.footer"
                class="text-muted-foreground min-w-0 flex-1 text-sm"
            >
                <slot name="footer" />
            </div>
            <SeeAllLink
                v-if="seeAll && placement === 'footer'"
                :see-all="seeAll"
            />
        </footer>
    </section>
</template>

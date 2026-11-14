<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    computed,
    getCurrentInstance,
    inject,
    provide,
    ref,
    useId,
    watch,
} from 'vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import {
    rendersAsLink,
    type ActionDescriptor,
} from '@/components/Kit/Action/actionPlacement';
import SeeAllLink from '@/components/Kit/Action/SeeAllLink.vue';
import { seeAllPlacement } from '@/components/Kit/Action/seeAll';
import Button from '@/components/Kit/Form/Button.vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';
import { cn } from '@/components/Kit/utils';
import {
    collectionFrameKey,
    type CollectionAction,
    type CollectionSectionData,
} from './collectionSection';

/*
 * A section that shows a collection (docs/collection-section.md): a list, a grid, a feed or table rows, in the default slot. The server's `section`
 * (CollectionSectionData, or a dashboard widget's frame) decides everything around them:
 * - the title, a heading at `level` (3 by default) that links to `seeAll`'s URL unless it says `linkTitle: false` (heading-coloured, underlined on hover);
 *   `description` under it;
 * - on the header's right, the Sort by menu (`sortBy`, while ready), the `#actions` slot, the descriptor `actions` (a GET action with the see-all's URL is
 *   left out, so the link is drawn once; one with a confirmation, intent or new tab stays) and a header see-all. Plain GET navigation is a compact link; every other
 *   action (a confirmation, `disabled`, a `dialog`, `panel` or `document` intent, `newTab`, another method) goes to one ActionBar after the links, which keeps
 *   its meaning: it asks first, shows its reason, opens the route modal or a new tab. A host that listens to `action` runs those itself (a `panel` needs it);
 *   otherwise the bar runs them;
 * - below the header, the `segments` (tabs such as All / New / In progress: links to the same page, the current one `aria-current`, named "New (4)" with a
 *   count) and the `#filters` slot;
 * - the body: the items, or the empty state (`empty`, or the `#empty` slot; an empty section never shows its items, even with neither), or the error with
 *   Retry; an error section also hides its `#actions` and `#filters` slots, as it hides its own actions, tabs and sort. With `maxHeight` the body
 *   scrolls (a focusable region named "<title> items"), and the footer stays in view under it;
 * - the footer: the `#footer` slot and, at the bottom right, the see-all ("All cases →"). An error section has no see-all, and its title is plain text.
 * It draws no card: on its own it is a bare section, for a grid or list whose items carry their own look (a grid of note cards), with the see-all under the
 * items at the right. CollectionCard puts it in a kit Card, with the same descriptor, by handing it a frame: the classes for its parts' padding and rules.
 * Choosing an order visits `sortBy.url` with `{ sort }` (its method; a partial visit for `sortBy.reload`, resetting only the scroll lists among them), puts
 * the old order back when refused, and remounts the items on the new one. `headless` drops the title bar (the section keeps the title as its accessible
 * name). `minHeight` holds the ready body's height while its content loads (a widget's `loadingHeight`).
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
// A descriptor with its method filled in (GET when left out), and whether it is plain navigation: a link in this tab with no confirmation, no intent the host
// or a new tab handles, enabled.
const descriptor = (action: CollectionAction): ActionDescriptor => ({
    ...action,
    method: action.method ?? 'get',
});
const navigates = (action: ActionDescriptor) =>
    rendersAsLink(action) && !action.newTab && action.intent !== 'document';
const actions = computed(() =>
    state.value === 'error'
        ? []
        : (props.section.actions ?? [])
              .map(descriptor)
              // The see-all's twin (plain navigation to its URL) is drawn once, as the see-all.
              .filter(
                  (action) =>
                      !(
                          props.section.seeAll &&
                          navigates(action) &&
                          action.url === props.section.seeAll.url
                      ),
              ),
);
const links = computed(() => actions.value.filter(navigates));
const operations = computed(() =>
    actions.value.filter((action) => !navigates(action)),
);
const emptyAction = computed(() =>
    props.section.empty?.action ? descriptor(props.section.empty.action) : null,
);
// ActionBar runs an action itself unless someone listens; this listens only when its own host does, and hands back the host's descriptor.
const emit = defineEmits<{ action: [action: CollectionAction] }>();
const instance = getCurrentInstance();
const original = (action: ActionDescriptor): CollectionAction =>
    [...(props.section.actions ?? []), props.section.empty?.action].find(
        (item) =>
            item &&
            (item.key ?? item.url) === (action.key ?? action.url) &&
            item.label === action.label,
    ) ?? action;
const relay = computed(() =>
    instance?.vnode.props?.onAction
        ? {
              action: (action: ActionDescriptor) =>
                  emit('action', original(action)),
          }
        : {},
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
const page = usePage();
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
        // The server's token for this choice, with the order, so the reload the visit leads to can tell it apart from the request alone (a dashboard
        // widget's, DashboardController). The headers go with the redirect's follow-up too.
        headers: sort.value.token
            ? {
                  'X-Kit-Sort': `${sort.value.token}&sort=${encodeURIComponent(next)}`,
              }
            : {},
        only: sort.value.reload,
        // A list (a scroll prop) starts again from its first page. Any other prop is not reset, so one the server sends as a merge prop (a dashboard widget's
        // frame, matched on its key) is swapped in where it was, and the rest of the page keeps what it had.
        reset: sort.value.reload.filter(
            (key) => key in (page.scrollProps ?? {}),
        ),
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
                    state !== 'error' &&
                    (sort ||
                        $slots.actions ||
                        links.length ||
                        operations.length ||
                        placement === 'header')
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
                <Link
                    v-for="action in links"
                    :key="action.key ?? action.url"
                    :href="action.url"
                    :aria-label="action.ariaLabel || undefined"
                    class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring rounded-md px-2 py-1 text-xs font-medium focus-visible:ring-2 focus-visible:outline-none"
                    >{{ action.label }}</Link
                >
                <ActionBar
                    v-if="operations.length"
                    :actions="operations"
                    v-on="relay"
                />
                <SeeAllLink
                    v-if="seeAll && placement === 'header'"
                    :see-all="seeAll"
                />
            </div>
        </header>
        <div
            v-if="state !== 'error' && (segments.length || $slots.filters)"
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
                            v-if="emptyAction && navigates(emptyAction)"
                            :href="emptyAction.url"
                            :aria-label="emptyAction.ariaLabel || undefined"
                            class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring mt-2 inline-flex min-h-11 items-center rounded-md px-4 text-sm font-medium focus-visible:ring-2 focus-visible:outline-none"
                            >{{ emptyAction.label }}</Link
                        >
                        <div v-else-if="emptyAction" class="mt-2">
                            <ActionBar :actions="[emptyAction]" v-on="relay" />
                        </div> </template
                ></slot>
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

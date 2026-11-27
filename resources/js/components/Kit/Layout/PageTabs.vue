<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    computed,
    inject,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';
import SectionIcon from '@/components/Kit/Layout/SectionIcon.vue';

// Tabs that are pages: each is a route link in a `<nav>` landmark, the current one marked `aria-current="page"`. Phones get the kit's InputSelect, which visits the chosen page,
// except a pill row of five or fewer, which stays pills.
// For panes inside one page use PaneTabs instead. `current` is the href of the page shown; when it is omitted, the tab flagged `current` wins.
// A tab with an `icon` (a SectionIcon name, a module page's Registry::modulePage() `icon`) draws it before its label; one without draws none.
// `#actions` sits at the row's end (right-aligned, never squeezed; beside the select on phones): a page with no visible heading puts its header actions there.
// Drawn inside a <Page>, these tabs are the page's: the <Page> then draws no `layout.navigation` tabs of its own, so there is one row.
const release = inject<(() => () => void) | null>('kit.page.tabs', null)?.();
if (release) onBeforeUnmount(release);
const props = defineProps<{
    tabs: {
        href: string;
        label: string;
        current?: boolean;
        icon?: string | null;
    }[];
    label: string;
    current?: string;
    /** `secondary`: the pill row, a module's own small sub-navigation under the page tabs (a nested module's pages). Never a third row, never a filter. */
    variant?: 'primary' | 'secondary';
    /** A select on phones even for a short pill row (the dashboard's top row). */
    phoneSelect?: boolean;
}>();
const pills = computed(() => props.variant === 'secondary');
// Five pills or fewer stay pills on a phone (the row scrolls sideways if it must); tabs, and a longer pill row, become a select there.
const phoneRow = computed(
    () => pills.value && !props.phoneSelect && props.tabs.length <= 5,
);
const active = computed(
    () => props.current ?? props.tabs.find((tab) => tab.current)?.href ?? null,
);
const options = computed(() =>
    props.tabs.map((tab) => ({ value: tab.href, label: tab.label })),
);
// A tab focused by keyboard is brought wholly into view: browsers leave a partly visible one where it is, cut off at the row's edge.
const reveal = (event: FocusEvent) => {
    if (event.target instanceof HTMLElement)
        event.target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
};
// The current tab is scrolled wholly into the row when the row first draws, and when the current page changes: a row wider than a phone would
// otherwise open at its start, with the current pill off-screen (Hubcommons #2016). Only the row scrolls, never the page.
const row = ref<HTMLElement | null>(null);
const showCurrent = () => {
    const list = row.value;
    const tab = list?.querySelector<HTMLElement>('[aria-current="page"]');
    if (!list || !tab || list.scrollWidth <= list.clientWidth) return;
    const start =
        tab.getBoundingClientRect().left -
        list.getBoundingClientRect().left +
        list.scrollLeft;
    const end = start + tab.getBoundingClientRect().width;
    if (start < list.scrollLeft) list.scrollLeft = start;
    else if (end > list.scrollLeft + list.clientWidth)
        list.scrollLeft = end - list.clientWidth;
};
onMounted(showCurrent);
watch(active, () => nextTick(showCurrent));
const tabClass = (href: string) =>
    pills.value
        ? [
              'focus-visible:outline-ring inline-flex min-h-8 items-center gap-1.5 rounded-full px-3 text-sm font-medium whitespace-nowrap focus-visible:outline-2 focus-visible:-outline-offset-2',
              href === active.value
                  ? 'bg-accent text-foreground'
                  : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
          ]
        : [
              'focus-visible:outline-ring inline-flex items-center gap-1.5 border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap focus-visible:outline-2 focus-visible:-outline-offset-2',
              href === active.value
                  ? 'border-primary text-foreground'
                  : 'text-muted-foreground hover:border-border hover:text-foreground border-transparent',
          ];
const go = (href: unknown) => {
    if (typeof href === 'string' && href !== active.value) router.visit(href);
};
</script>
<template>
    <div
        v-if="$slots.actions"
        class="flex items-center gap-3 shadow-[inset_0_-1px_0_var(--color-border)] max-sm:shadow-none"
        data-page-tabs-row
    >
        <nav :aria-label="label" class="min-w-0 flex-1" data-page-tabs>
            <div v-if="!phoneRow" class="sm:hidden">
                <InputSelect
                    :aria-label="label"
                    :model-value="active"
                    :options="options"
                    :clearable="false"
                    @update:model-value="go"
                />
            </div>
            <ul
                :class="[
                    phoneRow ? 'flex' : 'hidden sm:flex',
                    'gap-6 overflow-x-auto overflow-y-hidden overscroll-x-contain',
                ]"
                @focusin="reveal"
            >
                <li v-for="tab in tabs" :key="tab.href" class="shrink-0">
                    <Link
                        :href="tab.href"
                        :aria-current="tab.href === active ? 'page' : undefined"
                        :class="tabClass(tab.href)"
                        ><SectionIcon
                            v-if="tab.icon"
                            :name="tab.icon"
                            small
                            data-page-tab-icon
                        />{{ tab.label }}</Link
                    >
                </li>
            </ul>
        </nav>
        <div
            class="ml-auto flex shrink-0 items-center gap-2"
            data-page-tabs-actions
        >
            <slot name="actions" />
        </div>
    </div>
    <nav
        v-else
        :aria-label="label"
        data-page-tabs
        :data-page-pills="pills || undefined"
    >
        <div v-if="!phoneRow" class="sm:hidden">
            <InputSelect
                :aria-label="label"
                :model-value="active"
                :options="options"
                :clearable="false"
                @update:model-value="go"
            />
        </div>
        <!-- Scrolls sideways only when the tabs don't fit, never up and down, with no visible scrollbar (kit.css hides it). The baseline is an inset shadow, not a border the links
             overlap with -mb-px (that overlap overflowed the row by a pixel, and overflow-x forces overflow-y to scroll); the focus ring is inset so the row can't clip it. -->
        <ul
            :class="[
                phoneRow ? 'flex' : 'hidden sm:flex',
                'overflow-x-auto overflow-y-hidden overscroll-x-contain',
                pills
                    ? 'gap-1 py-2'
                    : 'gap-6 shadow-[inset_0_-1px_0_var(--color-border)]',
            ]"
            ref="row"
            @focusin="reveal"
        >
            <li v-for="tab in tabs" :key="tab.href" class="shrink-0">
                <Link
                    :href="tab.href"
                    :aria-current="tab.href === active ? 'page' : undefined"
                    :class="tabClass(tab.href)"
                    ><SectionIcon
                        v-if="tab.icon"
                        :name="tab.icon"
                        small
                        data-page-tab-icon
                    />{{ tab.label }}</Link
                >
            </li>
        </ul>
    </nav>
</template>

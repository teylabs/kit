<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, provide } from 'vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';
import SectionIcon from './SectionIcon.vue';

/*
 * The frame of the settings pages (the nearest folder Layout.vue puts it around a page): a sidebar listing every section the user may open, by group, with the
 * one being shown marked aria-current, and the page in the default slot on the right. On a phone the sidebar is a list at the top. It reads the page's
 * `layout.navigation` (decided on the server, current state included); with none there is no sidebar and the content stands alone.
 */
type Item = {
    label: string;
    url: string;
    current: boolean;
    icon?: string | null;
    badge?: string | null;
};
type Group = { label: string | null; items: Item[] };
const page = usePage<{ layout?: { navigation: Group[]; title?: string } }>();
// `label` names the sidebar (a module's, when <Page> frames a sidebar module); `select` makes it one select on phones with the groups as option groups (a
// module's sidebar, per the nested-navigation rule), in place of the list at the top that settings keep.
const props = withDefaults(
    defineProps<{ label?: string; select?: boolean }>(),
    { label: 'Settings sections', select: false },
);
const options = computed(() =>
    (page.props.layout?.navigation ?? []).map((group) => ({
        label: group.label ?? '',
        options: group.items.map((item) => ({
            value: item.url,
            label: item.label,
        })),
    })),
);
const current = computed(
    () =>
        (page.props.layout?.navigation ?? [])
            .flatMap((group) => group.items)
            .find((item) => item.current)?.url ?? null,
);
const go = (url: unknown) => {
    if (typeof url === 'string' && url !== current.value) router.visit(url);
};
// The sidebar is this page's navigation: the <Page> inside draws no tabs of it.
provide('kit.navigation.framed', true);
// On a phone the page's title comes first, above the section list: drawn here (hidden from assistive tech), while the page's own h1 stays its one heading, read
// but not shown below md.
const phoneTitle = computed(() =>
    page.props.layout?.navigation.length
        ? (page.props.layout?.title ?? null)
        : null,
);
provide('kit.settings.phone-title', phoneTitle);
</script>
<template>
    <div
        :class="[
            'grid gap-6 md:gap-10',
            // Two columns only beside a sidebar: with no navigation the content would fall into the 13rem sidebar column.
            page.props.layout?.navigation.length
                ? 'md:grid-cols-[13rem_minmax(0,1fr)]'
                : '',
        ]"
        data-settings-frame
    >
        <p
            v-if="phoneTitle"
            aria-hidden="true"
            class="font-display text-2xl font-(weight:--font-display-weight) tracking-tight md:hidden"
            data-settings-phone-title
        >
            {{ phoneTitle }}
        </p>
        <nav
            v-if="page.props.layout?.navigation.length"
            :aria-label="props.label"
            class="md:pt-1"
            data-settings-sidebar
        >
            <div v-if="props.select" class="sm:hidden" data-sidebar-select>
                <InputSelect
                    :aria-label="props.label"
                    :model-value="current"
                    :options="options"
                    :clearable="false"
                    @update:model-value="go"
                />
            </div>
            <div
                v-for="(group, index) in page.props.layout.navigation"
                :key="index"
                :class="['mb-4 last:mb-0', props.select && 'max-sm:hidden']"
            >
                <p
                    v-if="group.label"
                    class="text-muted-foreground mb-1 px-2 text-xs font-medium tracking-wide uppercase"
                >
                    {{ group.label }}
                </p>
                <ul class="flex flex-wrap gap-1 md:grid">
                    <li
                        v-for="item in group.items"
                        :key="item.url"
                        :data-section-url="item.url"
                    >
                        <Link
                            :href="item.url"
                            :aria-current="item.current ? 'page' : undefined"
                            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring aria-[current=page]:bg-accent aria-[current=page]:text-foreground flex min-h-9 items-center gap-2 rounded-md px-2 text-sm focus-visible:ring-2 focus-visible:outline-none aria-[current=page]:font-medium"
                        >
                            <SectionIcon
                                v-if="
                                    props.select
                                        ? !!item.icon
                                        : item.icon !== undefined
                                "
                                :name="item.icon ?? undefined"
                                class="size-4"
                            />
                            {{ item.label }}
                            <span
                                v-if="item.badge"
                                class="bg-muted text-muted-foreground ml-auto rounded-full px-2 py-0.5 text-xs font-normal"
                                data-planned-tag
                                >{{ item.badge }}</span
                            >
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>
        <div class="flex min-w-0 flex-col gap-6"><slot /></div>
    </div>
</template>

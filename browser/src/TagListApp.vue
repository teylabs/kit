<script setup lang="ts">
import TagList from '@/components/Kit/Text/TagList.vue';
import type { TagItem } from '@/components/Kit/Text/tagList';

// ?tag-list: the kit's TagList as a table row and a page header use it: a case's flags (one with a reason and a link) before its people's tags, a row
// with more than eight that folds the rest into "+N more", and one with max null that never folds.
const flag = (
    key: string,
    label: string,
    tone: string,
    tooltip: string | null = null,
    href: string | null = null,
): TagItem => ({ key, label, kind: 'flag', tone, tooltip, href });
const tag = (label: string): TagItem => ({
    key: `tag:${label.toLowerCase()}`,
    label,
    kind: 'tag',
    tone: 'neutral',
});
const row = [
    flag('stalled', 'Stalled', 'danger'),
    flag(
        'possible-duplicate',
        'Possible duplicate',
        'warning',
        'Looks like A-1042',
        '/cases/1042',
    ),
    tag('VIP'),
];
const many = [
    flag('unpaid', 'Unpaid', 'info'),
    ...[
        'North',
        'South',
        'East',
        'West',
        'Rush',
        'Q4 audit',
        'Legal',
        'Reviewed',
        'Spare',
    ].map(tag),
];
</script>
<template>
    <main class="mx-auto max-w-2xl space-y-6 p-6">
        <h1 class="text-lg font-semibold">Tag list</h1>
        <section data-case="row" class="w-64">
            <TagList :tags="row" label="Case A-2001 tags" />
        </section>
        <section data-case="many" class="w-64">
            <TagList :tags="many" label="Case A-2002 tags" />
        </section>
        <section data-case="all" class="w-64">
            <TagList :tags="many" :max="null" label="Case A-2003 tags" />
        </section>
        <section data-case="none"><TagList :tags="[]" /></section>
    </main>
</template>

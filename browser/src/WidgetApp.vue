<script setup lang="ts">
import { ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import SegmentLinks from '@/components/Kit/Layout/SegmentLinks.vue';
import Widget from '@/components/Kit/Layout/Widget.vue';

// Widget and SegmentLinks (?widget, &dark): a widget with a count and header actions, one with footer actions, one loading, one empty, one bare; segment
// links in both appearances, whose navigate handler takes over the visit and records the href in window.kitSegmentVisits.
const segments = [
    { key: 'all', label: 'All', href: '/notes', count: 12 },
    { key: 'red', label: 'Red', href: '/notes?colour=red', count: 3, tone: 'danger' },
    { key: 'blue', label: 'Blue', href: '/notes?colour=blue', tone: 'info' },
];
const current = ref('all');
const w = window as unknown as { kitSegmentVisits: string[] };
w.kitSegmentVisits = [];
const navigate = (event: MouseEvent, href: string) => {
    event.preventDefault();
    w.kitSegmentVisits.push(href);
    current.value = segments.find((segment) => segment.href === href)!.key;
};
</script>
<template>
    <main class="bg-background text-foreground grid max-w-3xl gap-4 p-6">
        <h1 class="text-lg font-semibold">Widgets</h1>
        <Widget :widget="{ key: 'notes', title: 'Notes', meta: 'Last 7 days', count: 2 }">
            <template #actions><Button type="button" variant="secondary">Add note</Button></template>
            <p>Two notes.</p>
        </Widget>
        <Widget :widget="{ key: 'footer', title: 'Footer actions' }" action-placement="footer">
            <template #actions><Button type="button" variant="secondary">See all</Button></template>
            <p>Body.</p>
        </Widget>
        <Widget :widget="{ key: 'loading', title: 'Loading' }" loading />
        <Widget :widget="{ key: 'empty', title: 'Empty', state: 'empty', empty: { title: 'No notes yet', body: 'Notes you add show here.', icon: 'inbox' }, status: { label: 'Quiet', tone: 'neutral' } }" />
        <Widget :widget="{ key: 'bare', title: 'Bare widget' }" bare><p>Bare body.</p></Widget>
        <SegmentLinks :segments="segments" :current="current" label="Filter notes" @navigate="navigate" />
        <SegmentLinks :segments="segments" current="red" label="Filter colours" appearance="neutral" @navigate="navigate" />
    </main>
</template>

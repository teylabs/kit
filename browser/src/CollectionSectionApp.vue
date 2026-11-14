<script setup lang="ts">
import CollectionSection from '@/components/Kit/Layout/CollectionSection.vue';
import SearchBox from '@/components/Kit/Search/SearchBox.vue';

// CollectionSection in each of its parts (?collection-section, `&dark` for the dark tokens; docs/collection-section.md), invented copy: a list card with segment tabs
// (All / New / In progress), a Sort by menu, a header action, 30 linked rows in a 12rem scrolling body and its see-all in the footer, its title linking there;
// a stat card with its see-all in the header, a count and a plain title (`linkTitle: false`); an empty card with the descriptor's empty state and one with
// the `#empty` slot; with `surface="none"`, a grid of six project cards with a header action and its see-all under the grid, and an empty one; and, first so its
// results open in view, a SearchBox, whose results end with "All results →".
const rows = Array.from({ length: 30 }, (_, i) => 101 + i);
const cases = {
    title: 'Ongoing cases',
    description: 'Cases someone is working on.',
    actions: [{ label: 'New case', url: '/@acme/cases/new', method: 'get', key: 'new' }],
    segments: [
        { key: 'all', label: 'All', url: '/@acme/cases?status=ongoing', current: true, count: 30 },
        { key: 'new', label: 'New', url: '/@acme/cases?status=ongoing&segment=new', count: 4 },
        { key: 'progress', label: 'In progress', url: '/@acme/cases?status=ongoing&segment=progress', count: 26 },
    ],
    sortBy: { label: 'Sort by', options: [{ key: 'touched', label: 'Last touched' }, { key: 'due', label: 'Due soonest' }], current: 'touched', url: '/@acme', method: 'get', reload: [] },
    seeAll: { label: 'All ongoing cases', url: '/@acme/cases?status=ongoing' },
    maxHeight: '12rem',
};
const stat = { title: 'Cases this week', seeAll: { label: 'All cases', url: '/@acme/cases?week=current', count: 42, placement: 'header' as const, linkTitle: false } };
const empty = { title: 'Overdue cases', state: 'empty' as const, empty: { variant: 'first-use', title: 'Nothing overdue', description: 'Cases past their due date show up here.' }, seeAll: { label: 'All overdue cases', url: '/@acme/cases?overdue=1' } };
const projectNames = ['Harbour', 'Lantern', 'Meadow', 'Orchard', 'Quarry', 'Willow'];
const projects = { title: 'Active projects', actions: [{ label: 'New project', url: '/@acme/projects/new', method: 'get', key: 'new' }], seeAll: { label: 'All active projects', url: '/@acme/projects?status=active' } };
const noProjects = { title: 'Archived projects', state: 'empty' as const, empty: { variant: 'first-use', title: 'Nothing archived', description: 'Projects you archive show up here.' }, seeAll: { label: 'All archived projects', url: '/@acme/projects?status=archived' } };
const slotted = { title: 'Flagged cases', state: 'empty' as const };
</script>
<template>
    <main class="grid min-h-screen content-start gap-6 bg-background p-4 text-foreground sm:p-8">
        <h1 class="text-lg font-medium">Cases</h1>
        <section data-search aria-label="Search cases"><SearchBox url="/search" /></section>
        <CollectionSection :section="cases" :level="2" data-case="list">
            <ul class="grid divide-y divide-border">
                <li v-for="row in rows" :key="row" class="py-2 text-sm"><a :href="`/@acme/cases/${row}`" class="font-medium">Case {{ row }}</a></li>
            </ul>
        </CollectionSection>
        <CollectionSection :section="stat" :level="2" data-case="stat"><p class="text-3xl font-semibold tabular-nums">42</p></CollectionSection>
        <CollectionSection :section="empty" :level="2" data-case="empty"><p>never shown</p></CollectionSection>
        <CollectionSection :section="slotted" :level="2" data-case="empty-slot">
            <p>never shown</p>
            <template #empty><p class="text-sm" data-own-empty>Flag a case to keep it here.</p></template>
        </CollectionSection>
        <CollectionSection :section="projects" surface="none" :level="2" data-case="grid">
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-project-grid>
                <li v-for="(name, i) in projectNames" :key="name" class="rounded-xl border bg-card p-4 text-card-foreground shadow-sm" data-project-card>
                    <a :href="`/@acme/projects/${i + 1}`" class="font-medium">{{ name }}</a>
                    <p class="text-sm text-muted-foreground">{{ 3 + i }} open tasks</p>
                </li>
            </ul>
        </CollectionSection>
        <CollectionSection :section="noProjects" surface="none" :level="2" data-case="grid-empty"><p>never shown</p></CollectionSection>
    </main>
</template>

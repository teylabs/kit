<script setup lang="ts">
import SummaryCard from '@/components/Kit/Card/SummaryCard.vue';
import PageSection from '@/components/Kit/Layout/PageSection.vue';
import SearchBox from '@/components/Kit/Search/SearchBox.vue';

// The see-all link outside the dashboard (?see-all, `&dark` for the dark tokens; docs/see-all.md), invented copy: a list PageSection with it in the footer
// (default placement), a stat PageSection with it in the header and a count, a SummaryCard list with it in the sticky footer, and, first so its results open
// in view, a SearchBox, whose results end with "All results →".
const seeAll = { label: 'All cases', url: '/@acme/cases' };
const rows = Array.from({ length: 12 }, (_, i) => `Case ${101 + i}`);
</script>
<template>
    <main class="grid min-h-screen content-start gap-6 bg-background p-4 text-foreground sm:p-8">
        <h1 class="text-lg font-medium">Cases</h1>
        <section data-search aria-label="Search cases"><SearchBox url="/search" /></section>
        <PageSection data-case="section-list" title="Open cases" :see-all="seeAll">
            <ul class="grid max-h-48 overflow-y-auto" aria-label="Open cases list" tabindex="0" data-list>
                <li v-for="row in rows" :key="row" class="border-t py-2 text-sm first:border-0">{{ row }}</li>
            </ul>
        </PageSection>
        <PageSection data-case="section-stat" title="Cases this week" :see-all="{ ...seeAll, count: 42, placement: 'header' }">
            <p class="text-3xl font-semibold tabular-nums">42</p>
        </PageSection>
        <SummaryCard data-case="summary-list" title="Recent cases" :see-all="{ label: 'All recent cases', url: '/@acme/cases?recent=1' }">
            <ul class="grid">
                <li v-for="row in rows.slice(0, 3)" :key="row" class="py-1 text-sm">{{ row }}</li>
            </ul>
        </SummaryCard>
    </main>
</template>

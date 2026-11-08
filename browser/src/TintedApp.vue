<script setup lang="ts">
import { provide, reactive } from 'vue';
import Card from '@/components/Kit/Card/Card.vue';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';
import FormCard from '@/components/Kit/Form/FormCard.vue';
import Page from '@/components/Kit/Layout/Page.vue';
import PageSection from '@/components/Kit/Layout/PageSection.vue';
import DataTable from '@/components/Kit/Table/DataTable.vue';

// Page grounds (?tinted, ?tinted=dark, ?tinted=white): main.ts adds the sample tinted theme (tests/fixtures/tinted-ground-theme.css, ground #f3f5e7) before
// the app mounts, except for `white`, which is the kit's default white ground. On it a DataTable, a FormCard, an EmptyState and a PageSection sit directly in
// a Page; then the nesting cases: a DataTable inside a PageSection, an EmptyState inside a Card, and a PageSection with surface="none". Invented names.
// The shell's Layout is stood in for by the value it provides, so the Page draws its heading (page chrome, on the ground).
provide('kit.shell', true);
const mode = new URLSearchParams(window.location.search).get('tinted');
if (mode === 'dark') document.documentElement.classList.add('dark');
const form = reactive({ processing: false, isDirty: false, hasErrors: false, recentlySuccessful: false, progress: null, reset() {} });
const column = (key: string, label: string) => ({ key, label, sortable: false, hideable: false, hiddenByDefault: false, align: null, srOnlyLabel: false, sortBy: null });
const table = {
    rows: [
        { id: 1, name: 'Ana Lopez', role: 'Grower' },
        { id: 2, name: 'Ben Okafor', role: 'Packer' },
    ],
    columns: [column('name', 'Name'), column('role', 'Role')],
    sort: null, search: null, filters: [], tabs: [], bulkActions: [],
    pagination: { page: 1, perPage: 25, total: 2, from: 1, to: 2, lastPage: 1 },
    emptyState: null,
};
</script>
<template>
    <main class="min-h-screen bg-background p-8 text-foreground" data-test-ground>
        <Page title="Members">
            <DataTable data-test="table" :table="table as never" search-label="Search members" row-label="name" />
            <form @submit.prevent>
                <FormCard data-test="form" title="Details" :form="form" :sticky="false" :warn-on-leave="false">
                    <label class="grid gap-2 text-sm">Name <input name="name" value="Hillside Growers" class="rounded-md border border-input bg-background px-3 py-2" /></label>
                </FormCard>
            </form>
            <EmptyState data-test="empty" title="No harvests yet" body="Log the first harvest to see it here." />
            <PageSection data-test="section" title="Notes">
                <p class="text-sm">The co-op meets on Thursdays.</p>
            </PageSection>
            <PageSection data-test="section-table" title="Packers">
                <DataTable data-test="nested-table" :table="table as never" :toolbar="false" row-label="name" />
            </PageSection>
            <Card data-test="card">
                <EmptyState data-test="nested-empty" compact title="No notes" />
            </Card>
            <PageSection data-test="section-none" title="On the ground" surface="none">
                <p class="text-sm">This section opts out.</p>
            </PageSection>
        </Page>
    </main>
</template>

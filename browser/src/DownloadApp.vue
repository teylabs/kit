<script setup lang="ts">
import { provide } from 'vue';
import Page from '@/components/Kit/Layout/Page.vue';
import PageSection from '@/components/Kit/Layout/PageSection.vue';
import RowActions from '@/components/Kit/Table/RowActions.vue';
import InertiaAction from '@/components/Kit/Action/InertiaAction.vue';

provide('kit.shell', true);
// Action `download` (?download): the same GET drawn as a header action, a section action, a lone row action, a row's menu item and an InertiaAction. Each must
// be a plain <a href download> with the Download icon, no target, no new-tab arrow and no Inertia visit (the stub Link carries data-stub-link).
const download = (key: string) => ({ key, label: 'Export CSV', url: `/reports/${key}.csv`, method: 'get', download: true });
const headerActions = [download('header'), { key: 'edit', label: 'Edit', url: '/reports/edit', method: 'get' }];
const sectionActions = [download('section')];
const rowMenu = [
    { key: 'view', label: 'View', url: '/reports/1', method: 'get', destructive: false, confirm: null },
    { ...download('row-menu'), destructive: false, confirm: null },
    { key: 'archive', label: 'Archive', url: '/reports/1/archive', method: 'post', destructive: false, confirm: null },
];
</script>
<template>
    <main>
        <Page title="Room revenue" :actions="headerActions" data-test-header>
            <PageSection title="By room" :actions="sectionActions" data-test-section>
                <p>Rows</p>
            </PageSection>
            <div data-test-row-lone>
                <RowActions :actions="[{ ...download('row'), destructive: false, confirm: null }]" label="Actions for Harbour" />
            </div>
            <div data-test-row-menu>
                <RowActions :actions="rowMenu" label="Actions for Garden" variant="overflow" />
            </div>
            <div data-test-inertia-action>
                <InertiaAction :action="download('single')" />
            </div>
        </Page>
    </main>
</template>

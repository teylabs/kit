<script setup lang="ts">
import { provide } from 'vue';
import Page from '@/components/Kit/Layout/Page.vue';
import PageAside from '@/components/Kit/Layout/PageAside.vue';
import PageGrid from '@/components/Kit/Layout/PageGrid.vue';
import PageSection from '@/components/Kit/Layout/PageSection.vue';

// A PageAside in a main-aside PageGrid (?page-aside, &tall): two PageSections ("feeds") that must sit the page's gap apart, beside a main column taller than
// the viewport. Short, the aside fits and sticks; with &tall its feeds are taller than the viewport, so it stays in the flow and scrolls with the page.
const tall = new URLSearchParams(location.search).has('tall');
const rows = tall ? 30 : 3;
provide('kit.shell', true);
</script>
<template>
    <main class="p-6">
        <Page title="Aside">
            <PageGrid preset="main-aside">
                <div class="flex flex-col gap-4">
                    <p v-for="n in 60" :key="n" class="rounded border p-3">Main row {{ n }}</p>
                </div>
                <PageAside label="Activity">
                    <PageSection title="Recent notes" data-test-feed="notes">
                        <p v-for="n in rows" :key="n">Note {{ n }}</p>
                    </PageSection>
                    <PageSection title="Recent changes" data-test-feed="changes">
                        <p v-for="n in rows" :key="n">Change {{ n }}</p>
                    </PageSection>
                </PageAside>
            </PageGrid>
        </Page>
    </main>
</template>

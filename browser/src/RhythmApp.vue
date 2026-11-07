<script setup lang="ts">
import { provide } from 'vue';
import Page from '@/components/Kit/Layout/Page.vue';
import PageGrid from '@/components/Kit/Layout/PageGrid.vue';
import PageGridItem from '@/components/Kit/Layout/PageGridItem.vue';
import PageSection from '@/components/Kit/Layout/PageSection.vue';
import Stack from '@/components/Kit/Layout/Stack.vue';
import BlockStack from '@/components/Kit/Layout/BlockStack.vue';

// The page rhythm (?rhythm, &flush for <Page flush>): a Page in the shell whose in-flow children must be spaced by one equal gap, and whose out-of-flow
// children (absolute, fixed, hidden, v-show, a closed dialog, a teleported block) must take none. Then a PageGrid of each preset and a span, and a Stack and
// the deprecated BlockStack. The shell's Layout is stood in for by the value it provides.
const flush = new URLSearchParams(location.search).has('flush');
provide('kit.shell', true);
</script>
<template>
    <main class="p-6">
        <Page title="Rhythm" :flush="flush">
            <div data-test-block="a" class="h-10 bg-muted">A</div>
            <span data-test-out class="absolute top-0 right-0">absolute</span>
            <div data-test-out class="fixed bottom-0 left-0">fixed</div>
            <div data-test-out hidden>hidden</div>
            <div v-show="false" data-test-out>v-show</div>
            <dialog data-test-out>closed dialog</dialog>
            <Teleport to="body"><div data-test-teleported>teleported</div></Teleport>
            <PageSection data-test-block="b" title="Section">
                <p>Section body</p>
            </PageSection>
            <PageGrid data-test-block="c" data-test-grid="none">
                <div>Whole row</div>
                <PageGridItem :span="3">Three</PageGridItem>
            </PageGrid>
            <PageGrid preset="main-aside" data-test-grid="main-aside">
                <div>Main</div>
                <div>Aside</div>
            </PageGrid>
            <PageGrid preset="halves" data-test-grid="halves">
                <div>One</div>
                <div>Two</div>
            </PageGrid>
            <PageGrid preset="thirds" data-test-grid="thirds">
                <div>One</div>
                <div>Two</div>
                <div>Three</div>
            </PageGrid>
            <Stack data-test-stack="stack"><p>1</p><p>2</p></Stack>
            <BlockStack data-test-stack="block-stack"><p>1</p><p>2</p></BlockStack>
        </Page>
    </main>
</template>

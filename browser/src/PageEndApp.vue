<script setup lang="ts">
import Page from '@/components/Kit/Layout/Page.vue';
import PageEnd from '@/components/Kit/Layout/PageEnd.vue';

// PageEnd after an out-of-flow helper (?page-end=hidden|fixed|absolute, &short for content under one and a half screens): it measures the end of the content
// from the last in-flow sibling before it, so a hidden, fixed or absolute helper just before it neither hides it on a long page nor shows it on a short one.
const query = new URLSearchParams(location.search);
const helper = query.get('page-end');
const short = query.has('short');
</script>
<template>
    <main>
        <Page title="Long page">
            <div data-test-content :style="{ height: short ? '200px' : '3000px' }">Content</div>
            <div v-if="helper === 'hidden'" hidden data-test-helper>helper</div>
            <div v-else-if="helper === 'fixed'" class="fixed top-0 left-0" data-test-helper>helper</div>
            <div v-else-if="helper === 'absolute'" class="absolute top-0 left-0" data-test-helper>helper</div>
            <PageEnd />
        </Page>
    </main>
</template>

<script setup lang="ts">
import Card from '@/components/Kit/Card/Card.vue';
import TextMarkdown from '@/components/Kit/Text/TextMarkdown.vue';
import { payloads, sample } from './markdownSamples';

if (new URLSearchParams(window.location.search).get('markdown') === 'dark') document.documentElement.classList.add('dark');

// TextMarkdown for run.mjs (?markdown, `=dark` for the dark tokens): a sample note with every GFM feature, for the prose styling, h3 headings, rel, axe
// and no page overflow at 390px; and payloads that each set window.pwned if anything in them ran.
</script>
<template>
    <main class="mx-auto max-w-3xl space-y-6 p-4">
        <h1 class="text-xl font-semibold">Markdown</h1>
        <h2 class="text-lg font-semibold">Sample note</h2>
        <Card data-test-sample class="p-4">
            <TextMarkdown :source="sample" />
        </Card>
        <h2 class="text-lg font-semibold">Payloads</h2>
        <Card data-test-payloads class="p-4">
            <TextMarkdown :source="payloads" />
        </Card>
    </main>
</template>

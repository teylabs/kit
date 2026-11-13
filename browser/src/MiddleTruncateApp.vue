<script setup lang="ts">
import MiddleTruncate from '@/components/Kit/Data/MiddleTruncate.vue';

// MiddleTruncate in boxes run.mjs resizes (?middle-truncate): a file name as a link, a URL, a path, a short name, an RTL name, an emoji name and a
// minTail, each in a [data-box] whose width the test sets. ?middle-truncate=dark under .dark.
if (new URLSearchParams(window.location.search).get('middle-truncate') === 'dark') document.documentElement.classList.add('dark');
const rows = [
    { key: 'file', value: 'paraframe-brand-guidelines-2026-final.pdf', kind: 'file' as const },
    { key: 'url', value: 'https://cdn.paraframe.studio/clients/acme/2026/brand/guidelines/final-v3.pdf?download=1', kind: 'auto' as const },
    { key: 'path', value: '/srv/clients/acme/2026/brand/guidelines/final-v3.pdf', kind: 'path' as const },
    { key: 'short', value: 'logo.svg', kind: 'auto' as const },
    { key: 'rtl', value: 'دليل-العلامة-التجارية-النهائي-للعميل.pdf', kind: 'file' as const },
    { key: 'emoji', value: '👨‍👩‍👧‍👦🇨🇦-family-reunion-photos-summer.jpeg', kind: 'file' as const },
];
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-sm text-foreground">
        <h1>Attachments</h1>
        <ul class="space-y-2">
            <li v-for="row in rows" :key="row.key" :data-row="row.key" data-box class="w-80 rounded-md border border-border p-2">
                <a v-if="row.key === 'file'" href="#file" class="block underline"><MiddleTruncate :value="row.value" :kind="row.kind" /></a>
                <MiddleTruncate v-else :value="row.value" :kind="row.kind" />
            </li>
            <li data-row="tail" data-box class="w-80 rounded-md border border-border p-2">
                <MiddleTruncate value="quarterly-board-report-appendix-b.xlsx" :min-tail="16" />
            </li>
        </ul>
    </main>
</template>

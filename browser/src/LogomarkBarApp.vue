<script setup lang="ts">
import Wordmark from '@/pages/Workspace/Partials/Wordmark.vue';

// The signed-in bar's mark (?logomark-bar), for run.mjs: a dark bar whose theme paints the logomark white (brightness 0, invert 1), with the Wordmark once
// without kit.brand.logomark_bar (the filtered logomark, as before) and once with it (the bar's own mark, unfiltered), and on the page (tone page), where
// logomark_bar is never used.
const svg = (fill: string) => 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="${fill}"/><circle cx="16" cy="16" r="7" fill="#ffffff"/></svg>`);
const logomark = svg('#1d4ed8');
const logomark_bar = svg('#f59e0b');
const plain = { name: 'Brightfield', url: '/', logomark, logomark_bar: null };
const withBar = { name: 'Brightfield', url: '/', logomark, logomark_bar };
</script>
<template>
    <div style="--shell-mark-brightness: 0; --shell-mark-invert: 1; --shell-mark-height: 2rem; --shell-mark-max-width: 8rem">
        <header class="flex h-16 items-center gap-6 bg-slate-900 px-6 text-white" data-bar="plain"><Wordmark :product="plain" /><span>without logomark_bar</span></header>
        <header class="flex h-16 items-center gap-6 bg-slate-900 px-6 text-white" data-bar="with"><Wordmark :product="withBar" /><span>with logomark_bar</span></header>
        <div class="flex h-16 items-center gap-6 px-6" data-page="with"><Wordmark :product="withBar" tone="page" /><span>page tone, logomark_bar set</span></div>
    </div>
</template>

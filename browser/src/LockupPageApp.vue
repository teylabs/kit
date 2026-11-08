<script setup lang="ts">
import Wordmark from '@/pages/Workspace/Partials/Wordmark.vue';

// The guest page's lockup (?lockup-page), for run.mjs: a mark drawn on a 48x48 canvas with its art in the middle (padded) beside a wordmark, a mark with a
// tight viewBox (cropped) beside the same wordmark, and a full lockup image (kit.brand.lockup as a path), shown as it is in place of both.
const svg = (viewBox: string, body: string) => 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="${viewBox}">${body}</svg>`);
const padded = svg('0 0 48 48', '<rect x="12" y="12" width="24" height="24" rx="6" fill="#1d4ed8"/>');
const cropped = svg('0 0 24 24', '<rect width="24" height="24" rx="6" fill="#1d4ed8"/>');
const wordmark = svg('0 0 160 32', '<text x="0" y="25" font-family="sans-serif" font-size="28" font-weight="700" fill="#64748b">Brightfield</text>');
const lockup = svg('0 0 200 40', '<rect width="40" height="40" rx="8" fill="#1d4ed8"/><text x="50" y="30" font-family="sans-serif" font-size="28" font-weight="700" fill="#64748b">Brightfield</text>');
const product = (logomark: string, extra = {}) => ({ name: 'Brightfield', url: '/', logomark, wordmark, lockup: true, ...extra });
</script>
<template>
    <div class="space-y-6 p-6" style="--shell-lockup-height: 2.5rem">
        <div data-lockup="padded"><Wordmark :product="product(padded)" tone="page" /></div>
        <div data-lockup="cropped"><Wordmark :product="product(cropped)" tone="page" /></div>
        <div data-lockup="image"><Wordmark :product="product(padded, { lockup_image: lockup })" tone="page" /></div>
    </div>
</template>

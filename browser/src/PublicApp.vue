<script setup lang="ts">
import MarkdownPage from '@/pages/Public/Markdown/Page.vue';
import PublicShell from '@/pages/Workspace/Partials/PublicShell.vue';

// The shell's public pages, for run.mjs (?public): PublicShell around the Markdown page, given server HTML (the server sanitizes; the page renders it as is).
const html = '<h2>Terms</h2><p>Plain <a href="/privacy">privacy</a> text.</p>';
</script>
<template>
    <PublicShell sign-in-url="/login" :footer-links="[{ label: 'Privacy', url: '/privacy' }, { label: 'Terms', url: '/terms' }]" footer-note="Northwind Farms Ltd">
        <template #nav><a href="/pricing">Pricing</a></template>
        <MarkdownPage title="Terms of service" description="The rules." :html="html" />
    </PublicShell>
</template>

<script setup lang="ts">
import Badge from '@/components/Kit/Feedback/Badge.vue';
import ColourDot from '@/components/Kit/Feedback/ColourDot.vue';
import ToneLabel from '@/components/Kit/Feedback/ToneLabel.vue';
import { TONES } from '@/components/Kit/Feedback/tones';

// Every named tone as a Badge (plain and with a dot), a ToneLabel and a ColourDot, for run.mjs: ?tones in light, ?tones=dark under .dark. Non-modal, so axe checks contrast.
if (new URLSearchParams(window.location.search).get('tones') === 'dark') document.documentElement.classList.add('dark');
</script>
<template>
    <main class="bg-background text-foreground min-h-screen p-4">
        <h1>Tones</h1>
        <ul class="grid gap-2">
            <li v-for="tone in TONES" :key="tone" class="flex items-center gap-3" :data-row="tone">
                <Badge :tone="tone">{{ tone }}</Badge>
                <Badge :tone="tone" dot size="md">{{ tone }} with a dot</Badge>
                <ToneLabel :label="`${tone} label`" :tone="tone" />
                <ColourDot :tone="tone" />
            </li>
        </ul>
        <p>Archived <ColourDot tone="slate" label="Archived" data-labelled /></p>
        <Badge tone="not-a-tone" data-unknown>Unknown</Badge>
    </main>
</template>

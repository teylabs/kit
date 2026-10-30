<script setup lang="ts">
// A Workbench in the filmstrip layout, as a Slides-like editor would list its slides: thumbnails with a decorative (empty) and a descriptive alt, an item without
// one, and the server's generated item type. Compiled by bin/typecheck-components.sh; never rendered.
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import WorkbenchNavigator from '@/components/Kit/Workbench/WorkbenchNavigator.vue';
import type { WorkbenchItem, WorkbenchThumbnail } from '@/components/Kit/Workbench/workbench';

const decorative: WorkbenchThumbnail = { src: '/decks/1/slides/1.png', alt: '' };
const items: WorkbenchItem[] = [
    { id: 's1', label: 'Title', thumbnail: decorative },
    { id: 's2', label: 'Revenue', thumbnail: { src: '/decks/1/slides/2.png', alt: 'Bar chart of revenue by quarter' } },
    { id: 's3', label: 'Thanks', thumbnail: null },
    { id: 's4', label: 'Appendix' },
];
// The server's item (types/kit.d.ts) is a navigator item as it arrives.
const fromServer: Tey.Kit.Features.Core.Data.WorkbenchItem = { id: 's5', label: 'Q&A', type: null, description: null, actions: [], thumbnail: { src: '/decks/1/slides/5.png', alt: '' } };
items.push(fromServer);
// @ts-expect-error a thumbnail needs its alt ('' when decorative)
const noAlt: WorkbenchThumbnail = { src: '/decks/1/slides/1.png' };
</script>
<template>
    <Workbench workbench-key="deck-editor" label="Deck" :items="items" navigator-layout="filmstrip" />
    <Workbench workbench-key="deck-editor" label="Deck" :items="items" navigator-layout="list" />
    <!-- @vue-expect-error a thumbnail without alt is rejected in the template too -->
    <Workbench workbench-key="deck-editor" label="Deck" :items="[{ id: 's1', label: 'Title', thumbnail: { src: '/decks/1/slides/1.png' } }]" />
    <!-- @vue-expect-error the layout is list or filmstrip -->
    <Workbench workbench-key="deck-editor" label="Deck" :items="items" navigator-layout="grid" />
    <WorkbenchNavigator workbench-key="deck-editor" label="Slides" layout="filmstrip" />
    <span v-if="noAlt">{{ noAlt }}</span>
</template>

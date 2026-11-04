<script setup lang="ts">
import { ref } from 'vue';
import SwatchPicker from '@/components/Kit/Form/SwatchPicker.vue';

// A SwatchPicker with two suggested swatches, a palette whose last entry is the default, and a preview, for run.mjs: ?swatch in light, ?swatch=dark under .dark.
if (new URLSearchParams(window.location.search).get('swatch') === 'dark') document.documentElement.classList.add('dark');
const accent = ref('');
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <h1>Branding</h1>
        <form data-form @submit.prevent>
            <SwatchPicker id="accent" v-model="accent" name="accent" label="Accent colour" :suggested="['#0e7490', '#B91C1C']" suggested-label="Suggested from your logo" :palette="['#1F2937', '#1D4ED8', '#15803D', '#0E7490', '#DEB100']" default-value="#DEB100">
                <template #preview="{ colour }"><p data-preview :data-colour="colour">Preview</p></template>
            </SwatchPicker>
        </form>
        <p data-value>{{ accent }}</p>
        <button type="button" data-after>After</button>
    </main>
</template>

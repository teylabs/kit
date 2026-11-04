<script setup lang="ts">
// SwatchPicker's v-model, swatches, labels, contrast options and preview slot, as a host would use them for an accent colour. Compiled by
// bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import SwatchPicker from '@/components/Kit/Form/SwatchPicker.vue';
import { contrastRatio, markColour, normaliseHex } from '@/components/Kit/Form/swatch';

const accent = ref('');
const ratio: number = contrastRatio('#000000', '#FFFFFF');
const mark: '#000000' | '#FFFFFF' = markColour('#DEB100');
const hex: string | null = normaliseHex('deb100');
</script>
<template>
    <SwatchPicker v-model="accent" label="Accent colour" :palette="['#1D4ED8']" />
    <SwatchPicker v-model="accent" name="accent" label="Accent colour" :suggested="['#0E7490']" suggested-label="From your logo" :palette="['#1D4ED8', '#DEB100']" default-value="#DEB100" background="#FFFFFF" :min-contrast="4.5">
        <template #preview="{ colour }"><span :style="{ color: colour }">{{ ratio }} {{ mark }} {{ hex }}</span></template>
    </SwatchPicker>
    <!-- @vue-expect-error a palette is required -->
    <SwatchPicker v-model="accent" label="Accent colour" />
    <!-- @vue-expect-error minContrast is a number -->
    <SwatchPicker v-model="accent" label="Accent colour" :palette="[]" min-contrast="high" />
</template>

<script setup lang="ts">
// The named tones on Badge (with and without a dot), ColourDot (decorative and labelled) and ToneLabel. Compiled by bin/typecheck-components.sh; never rendered.
import { TONES, toneOf, type Tone } from '@/components/Kit';
import Badge from '@/components/Kit/Feedback/Badge.vue';
import ColourDot from '@/components/Kit/Feedback/ColourDot.vue';
import ToneLabel from '@/components/Kit/Feedback/ToneLabel.vue';

const fromServer: Tone = toneOf('teal');
const all: readonly Tone[] = TONES;
</script>
<template>
    <Badge tone="purple" dot>Valuation</Badge>
    <Badge :tone="fromServer" size="md">Intake</Badge>
    <Badge v-for="tone in all" :key="tone" :tone="tone">{{ tone }}</Badge>
    <ColourDot tone="orange" />
    <ColourDot tone="slate" label="Archived" />
    <ToneLabel label="In progress" tone="info" />
    <ToneLabel label="Draft" :dot="false" />
</template>

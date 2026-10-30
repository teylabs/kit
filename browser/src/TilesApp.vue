<script setup lang="ts">
import { ref } from 'vue';
import RadioCardGroup, { type RadioCardOption } from '@/components/Kit/Form/RadioCardGroup.vue';

// RadioCardGroup as tiles, for run.mjs: ?tiles in light, ?tiles=dark under .dark. An option-slot group (custom content in each card) and an auto-column grid
// of image tiles named by their alt (the second disabled). Non-modal, so axe checks contrast.
if (new URLSearchParams(window.location.search).get('tiles') === 'dark') document.documentElement.classList.add('dark');
const plan = ref<string | number | null>('plus');
const swatch = ref<string | number | null>(null);
const plans: RadioCardOption[] = [{ value: 'basic', label: 'Basic' }, { value: 'plus', label: 'Plus' }, { value: 'team', label: 'Team', description: 'Up to 10 people' }];
const colours = ['#b91c1c', '#15803d', '#1d4ed8', '#a16207', '#7e22ce', '#0f766e', '#be185d', '#334155'];
const names = ['Red', 'Green', 'Blue', 'Amber', 'Purple', 'Teal', 'Pink', 'Slate'];
const svg = (colour: string) => `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="${colour}"/></svg>`)}`;
const tiles: RadioCardOption[] = colours.map((colour, i) => ({ value: names[i].toLowerCase(), image: { src: svg(colour), alt: `${names[i]} swatch` }, disabled: i === 1 }));
</script>
<template>
    <main class="grid min-h-screen max-w-3xl gap-6 bg-background p-4 text-foreground">
        <h1>Tiles</h1>
        <RadioCardGroup id="plan" v-model="plan" name="plan" label="Plan" :columns="3" :options="plans" data-tiles="plan">
            <template #option="{ option, checked, disabled }">
                <span class="text-base font-semibold" data-custom>{{ option.label }} plan</span>
                <span class="text-sm" :data-custom-state="checked ? 'checked' : disabled ? 'disabled' : 'idle'">{{ checked ? 'Current choice' : 'Available' }}</span>
            </template>
        </RadioCardGroup>
        <p data-plan>{{ plan }}</p>
        <RadioCardGroup id="swatch" v-model="swatch" name="swatch" label="Swatch" columns="auto" :options="tiles" data-tiles="swatch" />
        <p data-swatch>{{ swatch }}</p>
        <button type="button" data-after>After</button>
    </main>
</template>

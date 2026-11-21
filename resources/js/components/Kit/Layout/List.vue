<script setup lang="ts">
import { useInCard } from '@/components/Kit/Surface/surface';

// Divided rows (docs/list.md), in place of a hand-bordered <ul>. Inside a card (a Card, SettingsSection, ConnectionCard, DetailSection, FormCard, a widget) it
// draws no border of its own, only the dividers; straight on the page ground it draws its own card. Rows are ListRow. `label` names the list.
defineProps<{ label?: string }>();
const inCard = useInCard();
</script>
<template>
    <ul
        :aria-label="label"
        :class="['divide-border divide-y', !inCard && 'bg-card text-card-foreground rounded-xl border']"
        :data-list="inCard ? 'in-card' : 'surface'"
    >
        <slot />
    </ul>
</template>

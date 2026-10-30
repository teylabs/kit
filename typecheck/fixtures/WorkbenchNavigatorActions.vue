<script setup lang="ts">
// A Workbench with a navigator action (#navigator-actions): rendered first in the navigator, before the filter and list, so tab order follows the visual order.
// Compiled by bin/typecheck-components.sh; never rendered.
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import type { WorkbenchItem } from '@/components/Kit/Workbench/workbench';

const items: WorkbenchItem[] = [{ id: 's1', label: 'Slide 1' }];
const create = () => items.length;
</script>
<template>
    <Workbench workbench-key="deck" label="Deck" :items="items" current="s1" filterable>
        <template #navigator-actions><button type="button" @click="create">New slide</button></template>
    </Workbench>
</template>

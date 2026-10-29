<script setup lang="ts">
// A host that names its stage element attribute, both ways (the Workbench prop and the useWorkbench option), and turns the Workbench's keys off for a live session. Compiled by bin/typecheck-components.sh; never rendered.
import { useWorkbench } from '@/components/Kit/Workbench/useWorkbench';
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import { elementAttributeName, selectedRects } from '@/components/Kit/Workbench/workbenchStage';

const wb = useWorkbench('deck-editor', { elementSelector: '[data-block-id]' });
const attribute: string = wb.elementAttribute;
const rects = (viewport: HTMLElement) => selectedRects(viewport, wb.elements.map(element => element.id), elementAttributeName(attribute));
defineExpose({ rects });
</script>
<template>
    <Workbench workbench-key="deck-editor" label="Deck" element-attribute="data-block-id" :keys="false" />
</template>

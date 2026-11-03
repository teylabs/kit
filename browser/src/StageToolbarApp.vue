<script setup lang="ts">
import { onMounted } from 'vue';
import FloatingToolbar from '@/components/Kit/Layout/FloatingToolbar.vue';
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import { useWorkbench } from '@/components/Kit/Workbench/useWorkbench';
import type { ToolbarAction } from '@/components/Kit/Layout/toolbarAction';
import type { WorkbenchItem } from '@/components/Kit/Workbench/workbench';

// A stage-anchored FloatingToolbar whose items are wider than the stage (?stage-toolbar): the pill must fit inside the stage's insets, folding what doesn't fit,
// rather than sizing to its content and running past the stage's edge.
const items: WorkbenchItem[] = [{ id: 's1', label: 'Slide 1' }];
const tools: ToolbarAction[] = ['Bold', 'Italic', 'Underline', 'Strikethrough', 'Align left', 'Align centre', 'Align right', 'Bring forward', 'Send backward', 'Duplicate block', 'Delete block', 'Lock position', 'Group blocks', 'Ungroup blocks']
    .map((label, index) => ({ key: `t${index}`, label }));
const wb = useWorkbench('stage-toolbar');
onMounted(() => wb.selectElements([{ id: 'b1', type: 'text' }]));
</script>
<template>
    <main class="p-4">
        <h1>Deck</h1>
        <Workbench workbench-key="stage-toolbar" label="Deck" :items="items" current="s1">
            <template #stage>
                <div class="relative h-96 p-24">
                    <p data-workbench-element="b1" class="mx-auto w-40 border p-2">A text block</p>
                    <FloatingToolbar mode="stage" label="Block tools" :items="tools" />
                </div>
            </template>
        </Workbench>
    </main>
</template>

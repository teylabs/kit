<script setup lang="ts">
// A Workbench with no items yet: the stage's `#empty` slot renders the host's own empty state with the Workbench's action descriptors, running one through `act`.
// Compiled by bin/typecheck-components.sh; never rendered.
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';
import Workbench from '@/components/Kit/Workbench/Workbench.vue';

const actions: ActionDescriptor[] = [];
const run = (action: ActionDescriptor): string => action.label;
// `#empty` is declared, so its props are typed (an undeclared slot's props would be `any`).
type EmptyProps = Parameters<NonNullable<InstanceType<typeof Workbench>['$slots']['empty']>>[0];
type IsAny<T> = 0 extends 1 & T ? true : false;
const typed: IsAny<EmptyProps> = false;
const emptyProps: EmptyProps = { actions, act: run, label: 'Stage' };
void typed;
void emptyProps;
</script>
<template>
    <Workbench workbench-key="deck-editor" label="Deck" :items="[]" :actions="actions" @action="run">
        <template #empty="{ actions: stageActions, act, label }">
            <EmptyState :title="`${label}: no slides yet`" :actions="stageActions" @action="act" />
        </template>
        <template #stage><div /></template>
    </Workbench>
</template>

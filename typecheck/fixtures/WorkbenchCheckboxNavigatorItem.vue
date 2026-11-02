<script setup lang="ts">
// Checkbox menu items and the `#navigator-item` slot: an item's row menu carries `checked` descriptors (true, false), and the host renders each row's content with
// typed slot props, inside the kit's option. Compiled by bin/typecheck-components.sh; never rendered.
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import { menuItemRole, type ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { WorkbenchItem, WorkbenchNavigatorItemSlotProps } from '@/components/Kit/Workbench/workbench';

const skip: ActionDescriptor = { key: 'skip', label: 'Skip slide', url: '/slides/1/skip', method: 'post', checked: true };
const hide: ActionDescriptor = { key: 'hide', label: 'Hide notes', url: '/slides/1/hide', method: 'post', checked: false };
const role: 'menuitemcheckbox' | 'menuitem' = menuItemRole(skip);
// @ts-expect-error `checked` is a boolean, not a string
const wrong: ActionDescriptor = { label: 'Bad', url: '/bad', method: 'post', checked: 'yes' };
const items: WorkbenchItem[] = [{ id: '1', label: 'Slide 1', actions: [skip, hide] }];
function row(props: WorkbenchNavigatorItemSlotProps): string {
    const id: string = props.item.id;
    const selected: boolean = props.selected;
    const index: number = props.index;
    return `${id} ${selected} ${index}`;
}
void [role, wrong, row];
</script>
<template>
    <Workbench workbench-key="deck" label="Deck" :items="items">
        <template #navigator-item="{ item, selected, index }">
            <span>{{ (item.label satisfies string) }} {{ (selected satisfies boolean) }} {{ (index satisfies number) }}</span>
            <span><!-- @vue-expect-error the slot props are typed, not any: an item has no `nope` -->{{ item.nope }}</span>
        </template>
    </Workbench>
</template>

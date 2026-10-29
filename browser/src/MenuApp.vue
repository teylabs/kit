<script setup lang="ts">
import { DropdownMenu, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import ActionMenuItems from '@/components/Kit/Action/ActionMenuItems.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import MenuContent from '@/components/Kit/Menu/MenuContent.vue';

// A kit action menu with a destructive item (?menu), on the kit's default tokens, for run.mjs's open-menu axe run (color-contrast included). Non-modal, as the Workbench row menu is, so the page stays exposed to axe.
const act = (key: string, label: string, extra: Partial<ActionDescriptor> = {}): ActionDescriptor => ({ key, label, url: `/posts/${key}`, method: 'post', ...extra });
const actions: ActionDescriptor[] = [act('duplicate', 'Duplicate'), act('delete', 'Delete', { destructive: true })];
</script>
<template>
    <main class="p-4">
        <h1>Post</h1>
        <DropdownMenu :modal="false">
            <DropdownMenuTrigger data-menu-trigger class="rounded-md border px-3 py-1">Actions</DropdownMenuTrigger>
            <MenuContent><ActionMenuItems :actions="actions" /></MenuContent>
        </DropdownMenu>
    </main>
</template>

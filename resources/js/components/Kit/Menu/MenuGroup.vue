<script setup lang="ts">
import { DropdownMenuGroup, DropdownMenuLabel } from 'reka-ui';
import { DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { useId } from 'vue';

// One section of a grouped menu (menuGroups.ts): a separator before every section but the first, then a role="group" named by its label (a muted, small
// caps line the arrow keys skip). `on` false renders the items alone, so an ungrouped menu keeps its markup.
defineProps<{ on: boolean; first: boolean; label?: string | null }>();
const id = `menu-group-${useId()}`;
</script>
<template>
    <template v-if="on">
        <DropdownMenuSeparator v-if="!first" data-menu-group-separator />
        <DropdownMenuGroup
            :aria-labelledby="label ? id : undefined"
            :data-menu-group="label ?? ''"
        >
            <DropdownMenuLabel
                v-if="label"
                :id="id"
                class="text-muted-foreground px-2 pt-1.5 pb-1 text-xs font-medium tracking-wide uppercase"
                data-menu-group-label
                >{{ label }}</DropdownMenuLabel
            >
            <slot />
        </DropdownMenuGroup>
    </template>
    <slot v-else />
</template>

<script setup lang="ts">
defineOptions({ name: 'ActionLabel' });
import { ArrowUpRight, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import { actionIcons } from '@/components/Kit/Action/actionIcons';
import {
    actionIconName,
    opensNewTab,
} from '@/components/Kit/Action/actionPlacement';

// An action's face in every bar and menu: its icon, the label (truncating) and, for one that opens a new tab, a trailing arrow with screen-reader text.
// `menu`: the menu row draws the icon itself, once, and a new-tab arrow sits at the row's trailing edge, small and muted like a shortcut hint, never inside the label. `pending`: the action's request is in flight; a spinner takes the icon's place (data-action-pending).
// In a menu, an action's `description` shows under the label: muted, small and wrapping. It is hidden from the item's name and read as its description instead
// (the menu row's aria-describedby names `descriptionId`), so a screen reader hears "View listing, menu item, opens the public page as buyers see it".
const props = defineProps<{
    action: {
        label: string;
        icon?: string | null;
        newTab?: boolean | null;
        intent?: string | null;
        key?: string | null;
        description?: string | null;
    };
    menu?: boolean;
    descriptionId?: string;
    pending?: boolean;
}>();
const icon = computed(() =>
    props.menu ? undefined : actionIcons[actionIconName(props.action) ?? ''],
);
const newTab = computed(() => opensNewTab(props.action));
</script>
<template>
    <span
        v-if="menu && action.description"
        class="flex min-w-0 flex-1 flex-col align-top"
        data-action-captioned
        ><ActionLabel :action="{ ...action, description: null }" menu /><span
            :id="descriptionId"
            class="text-muted-foreground block text-xs text-pretty"
            aria-hidden="true"
            data-action-description
            >{{ action.description }}</span
        ></span
    >
    <span
        v-else
        :class="[
            'min-w-0 items-center gap-1.5 align-top leading-5',
            menu ? 'flex flex-1' : 'inline-flex',
        ]"
        ><LoaderCircle
            v-if="pending"
            class="size-4 shrink-0 animate-spin motion-reduce:animate-none"
            aria-hidden="true"
            data-action-pending
        /><component
            :is="icon"
            v-else-if="icon"
            class="size-4 shrink-0"
            aria-hidden="true"
        /><span :class="['truncate', menu && 'flex-1']">{{ action.label }}</span
        ><template v-if="newTab"
            ><ArrowUpRight
                :class="[
                    'size-3.5 shrink-0',
                    menu ? 'text-muted-foreground ml-auto' : 'opacity-70',
                ]"
                aria-hidden="true"
                data-new-tab-arrow
            /><span class="sr-only"> (opens in new tab)</span></template
        ></span
    >
</template>

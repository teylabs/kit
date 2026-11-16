<script setup lang="ts">
import type {
    DropdownMenuContentEmits,
    DropdownMenuContentProps,
} from 'reka-ui';
import {
    DropdownMenuContent,
    DropdownMenuPortal,
    useForwardPropsEmits,
} from 'reka-ui';
import { computed, useId, type HTMLAttributes } from 'vue';
import MenuSubjectRow from './MenuSubject.vue';
import type { MenuSubject } from './menuSubject';
import { useLayerTarget } from '@/components/Kit/Overlay/layer';
import { cn } from '@/components/Kit/utils';

// INTERNAL. The menu panel the kit's menus open: shadcn's DropdownMenuContent (same props, events and look), but portalled into the enclosing Modal's <dialog>
// when there is one (layer.ts), so a menu inside a Modal opens over it and takes clicks. Hosts keep using the shadcn component directly. `subject` draws the
// menu's subject header (MenuSubject.vue) above the items and describes the menu by it.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<
        DropdownMenuContentProps & {
            class?: HTMLAttributes['class'];
            subject?: MenuSubject | null;
        }
    >(),
    { sideOffset: 4 },
);
const emits = defineEmits<DropdownMenuContentEmits>();
const delegated = computed(() => {
    const { class: _class, subject: _subject, ...rest } = props;
    return rest;
});
const forwarded = useForwardPropsEmits(delegated, emits);
const target = useLayerTarget();
const subjectId = `menu-subject-${useId()}`;
</script>
<template>
    <DropdownMenuPortal :to="target">
        <DropdownMenuContent
            data-slot="dropdown-menu-content"
            v-bind="{ ...$attrs, ...forwarded }"
            :aria-describedby="
                subject
                    ? subject.caption
                        ? `${subjectId} ${subjectId}-caption`
                        : subjectId
                    : undefined
            "
            :class="
                cn(
                    'bg-popover text-popover-foreground data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 z-50 max-h-(--reka-dropdown-menu-content-available-height) min-w-[8rem] origin-(--reka-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md',
                    props.class,
                )
            "
        >
            <MenuSubjectRow v-if="subject" :id="subjectId" :subject="subject" />
            <slot />
        </DropdownMenuContent>
    </DropdownMenuPortal>
</template>

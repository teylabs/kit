<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, ChevronDown } from '@lucide/vue';
import {
    DropdownMenuItemIndicator,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
} from 'reka-ui';
import { computed, type Component, type PropType } from 'vue';
import MenuGroup from './MenuGroup.vue';
import MenuPanel from './MenuPanel.vue';
import MenuTrigger from './MenuTrigger.vue';
import { menuSections } from './menuGroups';
import type { MenuSubject } from './menuSubject';
import type { InlineDropdownItem } from './inlineDropdown';

// A link-styled trigger that opens a menu of choices, one of them current (docs/inline-dropdown.md): an optional icon, the current choice's label in medium
// weight and a chevron, with no field border, so it sits in a sentence or a header ("Sort by Due soonest ⌄"). The menu is the kit's MenuPanel: an optional
// subject header ("Sort cases by"), then the items as menuitemradio rows, the current one checked, in sections when the items carry `section`.
// URL or state driven: an item with `href` is a link (an Inertia visit, or a plain anchor with `external`); any other choice emits `update:modelValue` and
// `select` with its key. Choosing the current item does nothing. Caller attributes land on the trigger button.
defineOptions({ inheritAttrs: false });
const props = defineProps({
    items: { type: Array as PropType<InlineDropdownItem[]>, required: true },
    modelValue: { type: String as PropType<string | null>, default: null },
    /** The trigger's text when no item is current. */
    placeholder: { type: String, default: null },
    /** The trigger's accessible name before its label ("Sort by"), read as "Sort by: Due soonest". Read by assistive technology only (the trigger's `aria-label`). */
    label: { type: String, default: null },
    icon: {
        type: [Object, Function] as PropType<Component | null>,
        default: null,
    },
    subject: {
        type: [Object, String] as PropType<MenuSubject | string | null>,
        default: null,
    },
    align: { type: String as PropType<'start' | 'end'>, default: 'start' },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits<{
    'update:modelValue': [key: string];
    select: [key: string];
}>();
const current = computed(
    () => props.items.find((item) => item.key === props.modelValue) ?? null,
);
const menuSubject = computed<MenuSubject | null>(() =>
    typeof props.subject === 'string'
        ? { title: props.subject }
        : props.subject,
);
const name = computed(() =>
    props.label
        ? `${props.label}: ${current.value?.label ?? props.placeholder ?? ''}`
        : undefined,
);
const groups = computed(() => menuSections(props.items));
function choose(item: InlineDropdownItem) {
    if (item.disabled || item.href || item.key === props.modelValue) return;
    emit('update:modelValue', item.key);
    emit('select', item.key);
}
const itemClass =
    'focus:bg-accent focus:text-accent-foreground relative flex min-h-11 cursor-pointer items-center gap-2 rounded-sm py-1.5 ps-8 pe-2 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 sm:min-h-0';
</script>
<template>
    <MenuPanel
        :subject="menuSubject"
        :align="align"
        flip-align
        :side-offset="6"
        content-class="min-w-48 max-w-72"
    >
        <template #trigger>
            <MenuTrigger>
                <button
                    v-bind="$attrs"
                    type="button"
                    :disabled="disabled"
                    :aria-label="name"
                    class="text-foreground hover:text-primary focus-visible:ring-ring data-[state=open]:text-primary inline-flex min-w-0 cursor-pointer items-center gap-1.5 rounded-sm px-1 py-0.5 text-sm outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-60"
                    data-inline-dropdown
                >
                    <component
                        :is="icon"
                        v-if="icon"
                        class="text-muted-foreground size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span
                        class="truncate font-medium"
                        data-inline-dropdown-label
                        >{{ current?.label ?? placeholder }}</span
                    >
                    <ChevronDown
                        class="text-muted-foreground size-4 shrink-0"
                        aria-hidden="true"
                    />
                </button>
            </MenuTrigger>
        </template>
        <DropdownMenuRadioGroup :model-value="modelValue ?? undefined">
            <MenuGroup
                v-for="(section, index) in groups.sections"
                :key="section.key"
                :on="groups.grouped"
                :first="index === 0"
                :label="section.label"
            >
                <DropdownMenuRadioItem
                    v-for="{ action: item } in section.items"
                    :key="item.key"
                    :value="item.key"
                    :disabled="item.disabled"
                    :as-child="!!item.href && !item.disabled"
                    :class="itemClass"
                    :data-inline-dropdown-item="item.key"
                    @select="choose(item)"
                >
                    <template v-if="item.href && !item.disabled">
                        <a v-if="item.external" :href="item.href">
                            <span
                                class="absolute start-2 flex size-4 items-center justify-center"
                                ><DropdownMenuItemIndicator
                                    ><Check
                                        class="size-4"
                                        aria-hidden="true" /></DropdownMenuItemIndicator
                            ></span>
                            <span class="truncate">{{ item.label }}</span>
                        </a>
                        <Link v-else :href="item.href">
                            <span
                                class="absolute start-2 flex size-4 items-center justify-center"
                                ><DropdownMenuItemIndicator
                                    ><Check
                                        class="size-4"
                                        aria-hidden="true" /></DropdownMenuItemIndicator
                            ></span>
                            <span class="truncate">{{ item.label }}</span>
                        </Link>
                    </template>
                    <template v-else>
                        <span
                            class="absolute start-2 flex size-4 items-center justify-center"
                            ><DropdownMenuItemIndicator
                                ><Check
                                    class="size-4"
                                    aria-hidden="true" /></DropdownMenuItemIndicator
                        ></span>
                        <span class="truncate">{{ item.label }}</span>
                    </template>
                </DropdownMenuRadioItem>
            </MenuGroup>
        </DropdownMenuRadioGroup>
    </MenuPanel>
</template>

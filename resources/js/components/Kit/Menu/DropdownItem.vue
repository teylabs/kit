<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { DropdownMenuItem } from 'reka-ui';
import { useMenuItemClass } from '@/components/Kit/Action/menuItemClass';
import { cn } from '@/components/Kit/utils';
import { useId, type Component } from 'vue';
import { useMenuItem, useMenuPanel } from '@/components/Kit/Menu/useMenuPanel';

// One entry in a kit menu (DropdownItem.md): a button, a plain link or an Inertia link, with an optional icon and caption, or the caller's own control in the `wrapper` slot. Must sit inside a kit menu. Caller attributes land on the inner control.
// `active` is declared and does nothing (an owner question in the spec); a link left at the `#` placeholder is not followed.
defineOptions({ inheritAttrs: false });
// `@chosen` fires after the menu has closed: use it, not `@click`, for work that moves or re-renders the item's own row. It is a prop (`onChosen`), not an emit:
// the closed menu has unmounted the item by then, and Vue drops an unmounted component's emits, so the handler is called from the props it was given.
const props = withDefaults(
    defineProps<{
        href?: string;
        as?: string | Component | null;
        inertia?: boolean;
        active?: boolean;
        onChosen?: () => void;
        destructive?: boolean;
    }>(),
    { href: '#', as: null, onChosen: undefined, destructive: false },
);

const menu = useMenuPanel();
const item = useMenuItem();
const controlClass =
    'block w-full px-4 py-2 text-left text-sm/5 text-popover-foreground outline-hidden';
// Icons keep their size; the label takes the rest and wraps only as words, never pushing an icon onto its own line.
const contentClass = 'flex items-start gap-2 [&>svg]:shrink-0';
const captionClass = 'block text-xs text-pretty text-muted-foreground';
// The `caption` is hidden from the item's name and read as its description (aria-describedby), so the name stays the label.
const captionId = `dropdown-caption-${useId()}`;
const close = () => menu?.close();
const chosen = () =>
    menu ? menu.afterClose(() => props.onChosen?.()) : props.onChosen?.();
// A ButtonGroup's `itemClass`, merged last onto the inner control (so its padding replaces the control's own, not adds to it); with the `wrapper` slot there is
// no inner control, so it goes on the row.
const itemClass = useMenuItemClass();
// `destructive`: red text (the kit's destructive text token) in place of the popover's, as every kit menu draws a destructive item.
const rowControlClass = () =>
    cn(
        controlClass,
        props.destructive &&
            'text-destructive-text focus:text-destructive-text',
        itemClass(),
    );
</script>
<template>
    <DropdownMenuItem
        as="div"
        class="data-highlighted:bg-accent data-highlighted:text-accent-foreground flex items-start outline-hidden transition-colors"
        :class="$slots.wrapper ? itemClass() : undefined"
        :aria-describedby="$slots.caption ? captionId : undefined"
        @click.capture="item.onClickCapture"
        @select="item.onSelect"
    >
        <slot name="wrapper" :close="close">
            <button
                v-if="as === 'button' && !inertia"
                v-bind="$attrs"
                @click="chosen"
                :class="rowControlClass()"
                tabindex="-1"
                type="button"
            >
                <span :class="contentClass">
                    <span class="shrink-0"><slot name="icon" /></span>
                    <span class="min-w-0">
                        <span class="block"><slot /></span>
                        <span
                            v-if="$slots.caption"
                            :id="captionId"
                            aria-hidden="true"
                            :class="captionClass"
                            ><slot name="caption"
                        /></span>
                    </span>
                </span>
            </button>
            <a
                v-else-if="as === 'a'"
                v-bind="$attrs"
                :class="rowControlClass()"
                :href="href"
                tabindex="-1"
            >
                <span :class="contentClass">
                    <span class="shrink-0"><slot name="icon" /></span>
                    <span class="min-w-0">
                        <span class="block"><slot /></span>
                        <span
                            v-if="$slots.caption"
                            :id="captionId"
                            aria-hidden="true"
                            :class="captionClass"
                            ><slot name="caption"
                        /></span>
                    </span>
                </span>
            </a>
            <Link
                v-else
                v-bind="$attrs"
                :as="as ?? 'a'"
                :class="rowControlClass()"
                :href="href"
                tabindex="-1"
            >
                <span :class="contentClass">
                    <span class="shrink-0"><slot name="icon" /></span>
                    <span class="min-w-0">
                        <span class="block"><slot /></span>
                        <span
                            v-if="$slots.caption"
                            :id="captionId"
                            aria-hidden="true"
                            :class="[captionClass, 'mt-1']"
                            ><slot name="caption"
                        /></span>
                    </span>
                </span>
            </Link>
        </slot>
    </DropdownMenuItem>
</template>

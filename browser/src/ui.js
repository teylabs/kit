import { defineComponent, h } from 'vue';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, DialogTrigger, DropdownMenuContent as RekaContent, DropdownMenuLabel as RekaLabel, DropdownMenuPortal } from 'reka-ui';
import { DropdownMenuCheckboxItem as RekaCheckboxItem, DropdownMenuItem as RekaItem, DropdownMenuItemIndicator as RekaIndicator, DropdownMenuRoot, DropdownMenuSeparator as RekaSeparator, DropdownMenuTrigger as RekaTrigger } from 'reka-ui';

// Stand-ins for the shadcn components the tested kit components import. Menus are reka's own, so a menu opens, takes focus and dismisses as in an app.
const pass = (tag, cls = '') => defineComponent({ inheritAttrs: false, setup: (_, { slots, attrs }) => () => h(tag, { ...attrs, class: [cls, attrs.class] }, slots.default?.()) });
export const Label = pass('label', 'text-sm font-medium');
export const Input = defineComponent({ inheritAttrs: false, props: ['modelValue'], emits: ['update:modelValue'], setup: (p, { attrs, emit }) => () => h('input', { ...attrs, value: p.modelValue, onInput: (e) => emit('update:modelValue', e.target.value), class: ['h-9 w-full rounded-md border px-3', attrs.class] }) });
export const Spinner = pass('span');
export const DropdownMenu = DropdownMenuRoot;
export const DropdownMenuTrigger = RekaTrigger;
export const DropdownMenuItem = RekaItem;
export const DropdownMenuSeparator = RekaSeparator;
// shadcn-vue's DropdownMenuCheckboxItem: reka's checkbox item (role="menuitemcheckbox", aria-checked) with the check indicator in a left gutter. `select` passes through.
export const DropdownMenuCheckboxItem = defineComponent({
    inheritAttrs: false,
    props: { modelValue: { type: Boolean, default: undefined } },
    setup: (p, { slots, attrs }) => () => h(RekaCheckboxItem, { ...attrs, modelValue: p.modelValue, class: ['relative flex items-center gap-2 py-1.5 pr-2 pl-8 text-sm', attrs.class] }, () => [
        h('span', { class: 'absolute left-2 flex size-3.5 items-center justify-center' }, h(RekaIndicator, null, () => h('span', { 'aria-hidden': 'true', 'data-check-indicator': '' }, '✓'))),
        slots.default?.(),
    ]),
});
// The shell's (shell.html): the menus' content in a portal, and the drawer's sheet as reka's dialog.
export const DropdownMenuContent = defineComponent({ inheritAttrs: false, setup: (_, { slots, attrs }) => () => h(DropdownMenuPortal, null, () => h(RekaContent, { sideOffset: 6, ...attrs, class: ['z-50 rounded-md border bg-popover p-1 text-popover-foreground shadow-md', attrs.class] }, slots.default)) });
export const DropdownMenuLabel = defineComponent({ inheritAttrs: false, setup: (_, { slots, attrs }) => () => h(RekaLabel, { ...attrs, class: ['px-2 py-1.5 text-sm', attrs.class] }, slots.default) });
export const Sheet = DialogRoot;
export const SheetTrigger = DialogTrigger;
export const SheetTitle = DialogTitle;
export const SheetDescription = DialogDescription;
export const SheetClose = DialogClose;
export const SheetContent = defineComponent({ inheritAttrs: false, setup: (_, { slots, attrs }) => () => h(DialogPortal, null, () => [h(DialogOverlay, { class: 'fixed inset-0 z-40 bg-black/40' }), h(DialogContent, { ...attrs, class: ['fixed inset-y-0 right-0 z-50 w-80 bg-background p-4', attrs.class] }, slots.default)]) });
export default Spinner;

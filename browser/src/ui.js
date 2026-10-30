import { defineComponent, h } from 'vue';
import { CheckboxIndicator, CheckboxRoot, DropdownMenuCheckboxItem as RekaCheckboxItem, DropdownMenuItem as RekaItem, DropdownMenuItemIndicator as RekaIndicator, DropdownMenuRoot, DropdownMenuSeparator as RekaSeparator, DropdownMenuTrigger as RekaTrigger } from 'reka-ui';

// Stand-ins for the shadcn components the tested kit components import. Menus are reka's own, so a menu opens, takes focus and dismisses as in an app.
const pass = (tag, cls = '') => defineComponent({ inheritAttrs: false, setup: (_, { slots, attrs }) => () => h(tag, { ...attrs, class: [cls, attrs.class] }, slots.default?.()) });
export const Button = pass('button', 'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium h-9 px-4 py-2');
export const Label = pass('label', 'text-sm font-medium');
export const Input = defineComponent({ inheritAttrs: false, props: ['modelValue'], emits: ['update:modelValue'], setup: (p, { attrs, emit }) => () => h('input', { ...attrs, value: p.modelValue, onInput: (e) => emit('update:modelValue', e.target.value), class: ['h-9 w-full rounded-md border px-3', attrs.class] }) });
export const Spinner = pass('span');
// shadcn-vue's Checkbox: reka's CheckboxRoot (a button, role="checkbox") with its indicator; attributes and `required` pass through to the root.
export const Checkbox = defineComponent({
    inheritAttrs: false,
    props: { modelValue: { type: [Boolean, String], default: undefined } },
    emits: ['update:modelValue'],
    setup: (p, { attrs, emit }) => () => h(CheckboxRoot, { ...attrs, modelValue: p.modelValue, 'onUpdate:modelValue': (v) => emit('update:modelValue', v), class: ['peer size-4 shrink-0 rounded-[4px] border border-input', attrs.class] }, () => h(CheckboxIndicator, { class: 'flex items-center justify-center' }, () => h('span', { 'aria-hidden': 'true' }, '✓'))),
});
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
export default Spinner;

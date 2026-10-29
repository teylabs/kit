import { defineComponent, h } from 'vue';
import { DropdownMenuItem as RekaItem, DropdownMenuRoot, DropdownMenuSeparator as RekaSeparator, DropdownMenuTrigger as RekaTrigger } from 'reka-ui';

// Stand-ins for the shadcn components the tested kit components import. Menus are reka's own, so a menu opens, takes focus and dismisses as in an app.
const pass = (tag, cls = '') => defineComponent({ inheritAttrs: false, setup: (_, { slots, attrs }) => () => h(tag, { ...attrs, class: [cls, attrs.class] }, slots.default?.()) });
export const Button = pass('button', 'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium h-9 px-4 py-2');
export const Label = pass('label', 'text-sm font-medium');
export const Input = defineComponent({ inheritAttrs: false, props: ['modelValue'], emits: ['update:modelValue'], setup: (p, { attrs, emit }) => () => h('input', { ...attrs, value: p.modelValue, onInput: (e) => emit('update:modelValue', e.target.value), class: ['h-9 w-full rounded-md border px-3', attrs.class] }) });
export const Spinner = pass('span');
export const Checkbox = pass('span');
export const DropdownMenu = DropdownMenuRoot;
export const DropdownMenuTrigger = RekaTrigger;
export const DropdownMenuItem = RekaItem;
export const DropdownMenuSeparator = RekaSeparator;
export default Spinner;

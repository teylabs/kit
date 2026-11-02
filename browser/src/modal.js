import { defineComponent, h } from 'vue';

// A stand-in for @inertiaui/modal-vue, for pages that import the kit Modal without opening one (the Workbench's drawer and overlay navigator stay closed at xl).
export const Modal = defineComponent({ setup: () => () => null });
export const visitModal = () => {};
export const ModalLink = defineComponent({ props: ['href'], setup: (p, { slots }) => () => h('a', { href: p.href }, slots.default?.()) });

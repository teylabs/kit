import { defineComponent, h, ref } from 'vue';

// A stand-in for @inertiaui/modal-vue, for pages that import the kit Modal without opening one (the Workbench's drawer and overlay navigator stay closed at xl).
export const Modal = defineComponent({ setup: () => () => null });
export const visitModal = () => {};
export const ModalLink = defineComponent({ props: ['href'], setup: (p, { slots }) => () => h('a', { href: p.href }, slots.default?.({ loading: false })) });
// No modal is ever open here, so the Workbench's actions bar is never inert.
export const useModalStack = () => ({ stack: ref([]) });

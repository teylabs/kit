import { defineComponent, h, ref } from 'vue';

// A stand-in for @inertiaui/modal-vue. Pages that import the kit Modal without opening one render nothing. A local, named Modal (the kit Modal's
// `v-model:open`) opens at its `max-width` (the kit Modal's size), its `panel-classes` on the panel as the real package puts them, through visitModal('#name') as a native <dialog> in the top layer, its slot inside, and closes with close() or onClose.
const named = new Map();
const widths = { md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-xl', '2xl': 'max-w-2xl', '4xl': 'max-w-4xl', '7xl': 'max-w-7xl' };
export const Modal = defineComponent({
    props: ['name', 'panelClasses', 'maxWidth'],
    setup(props, { slots, expose }) {
        const open = ref(false);
        const dialog = ref();
        let onClose = () => {};
        const close = () => {
            if (!open.value) return;
            open.value = false;
            onClose();
        };
        if (props.name) named.set(props.name, { show: (options = {}) => { onClose = options.onClose ?? (() => {}); open.value = true; queueMicrotask(() => dialog.value?.showModal?.()); return { close }; } });
        expose({ close });
        return () => (open.value ? h('dialog', { ref: dialog, class: `m-auto w-full ${widths[props.maxWidth] ?? 'max-w-2xl'} bg-transparent p-0`, onCancel: (event) => { event.preventDefault(); close(); } }, props.panelClasses ? h('div', { class: props.panelClasses }, slots.default?.({ close })) : slots.default?.({ close })) : null);
    },
});
export const visitModal = (target, options) => named.get(String(target).replace(/^#/, ''))?.show(options);
export const ModalLink = defineComponent({ props: ['href'], setup: (p, { slots }) => () => h('a', { href: p.href }, slots.default?.({ loading: false })) });
// No modal is ever open here, so the Workbench's actions bar is never inert.
export const useModalStack = () => ({ stack: ref([]) });

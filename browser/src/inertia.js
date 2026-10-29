import { defineComponent, h } from 'vue';

export const Link = defineComponent({ props: ['href'], setup: (p, { slots }) => () => h('a', { href: p.href }, slots.default?.()) });
export const Form = defineComponent({ setup: (_, { slots }) => () => h('form', slots.default?.({})) });
export const router = { visit() {}, get() {}, post() {}, reload() {}, on: () => () => {} };
export const usePage = () => ({ props: {}, url: '/' });
export const useFormContext = () => null;
export const useForm = (data = {}) => ({ ...data, errors: {}, processing: false, submit() {}, post() {}, reset() {} });
export const http = { getClient: () => ({ request: () => Promise.reject(new Error('offline')) }) };

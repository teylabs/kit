import { defineComponent, h, reactive } from 'vue';

// One reactive page, as Inertia v3's usePage() gives: `props` (shared props such as Fortify's `status`) and `flash` (Inertia::flash()).
const page = reactive({ props: {}, flash: {}, url: '/' });
const listeners = {};
const fire = (name, detail) => (listeners[name] ?? []).slice().forEach((callback) => callback(new CustomEvent(`inertia:${name}`, { detail })));

export const Link = defineComponent({ props: ['href'], setup: (p, { slots }) => () => h('a', { href: p.href }, slots.default?.()) });
export const Form = defineComponent({ setup: (_, { slots }) => () => h('form', slots.default?.({})) });
export const router = {
    get() {}, post() {}, reload() {},
    on: (name, callback) => {
        (listeners[name] ??= []).push(callback);
        return () => (listeners[name] = listeners[name].filter((c) => c !== callback));
    },
    // A visit in Inertia v3's order (core Request.send, Response.process): `start` then onStart; the response swaps the page (props, then flash); `flash` when the
    // flash is not empty; `success`, then onSuccess; finally `finish`, then onFinish. `respond` resolves with the new page's { props, flash }.
    async visit(_url, { onStart, onSuccess, onFinish } = {}, respond) {
        fire('start', { visit: {} });
        onStart?.();
        const next = await respond;
        page.props = next.props ?? {};
        page.flash = next.flash ?? {};
        if (Object.keys(page.flash).length > 0) fire('flash', { flash: page.flash });
        fire('success', { page });
        onSuccess?.(page);
        fire('finish', { visit: {} });
        onFinish?.();
    },
};
export const usePage = () => page;
export const useFormContext = () => null;
export const useForm = (data = {}) => reactive({ ...data, errors: {}, processing: false, submit() {}, post() {}, reset() {} });
// Inertia's HTTP client: offline, unless a page installs `window.kitHttpClient` (?workbench-reorder does, with v3's XHR client's shape: `data` is the raw text).
export const http = { getClient: () => window.kitHttpClient ?? { request: () => Promise.reject(new Error('offline')) } };

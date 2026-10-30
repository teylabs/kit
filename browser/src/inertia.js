import { defineComponent, h, inject, provide, reactive, ref } from 'vue';

// One reactive page, as Inertia v3's usePage() gives: `props` (shared props such as Fortify's `status`) and `flash` (Inertia::flash()).
const page = reactive({ props: {}, flash: {}, url: '/' });
const listeners = {};
const fire = (name, detail) => (listeners[name] ?? []).slice().forEach((callback) => callback(new CustomEvent(`inertia:${name}`, { detail })));

export const Link = defineComponent({ props: ['href'], setup: (p, { slots }) => () => h('a', { href: p.href }, slots.default?.()) });
export const Head = defineComponent({ props: ['title'], setup: () => () => null });
// Inertia v3's <Form>, as far as the auth pages use it (vue3 Form, core Response.process): submit calls onStart; the test answers through
// window.kitRespond({ status, headers, errors, props }). A status of 400 or more is an HTTP exception: onHttpException(response) runs, and unless it returns
// false Inertia's error modal opens (here, as there, an <iframe> in an overlay). Otherwise errors are set (onError, then resetOnError) or cleared (onSuccess,
// then resetOnSuccess), and the old errors stay until then. onFinish ends it. The slot and the form context get errors, processing, clearErrors and reset.
const FormContext = Symbol('form');
export const Form = defineComponent({
    inheritAttrs: false,
    props: { action: String, method: String, resetOnError: { type: [Boolean, Array], default: false }, resetOnSuccess: { type: [Boolean, Array], default: false }, onStart: Function, onError: Function, onSuccess: Function, onFinish: Function, onHttpException: Function },
    setup(props, { slots, attrs, expose }) {
        const element = ref(null);
        const reset = (...fields) => [...(element.value?.elements ?? [])].filter((field) => field.name && field.type !== 'hidden' && (fields.length === 0 || fields.includes(field.name))).forEach((field) => {
            field.value = '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
        const maybeReset = (option) => option === true ? reset() : Array.isArray(option) && option.length > 0 ? reset(...option) : undefined;
        const form = reactive({ errors: {}, processing: false, clearErrors: () => (form.errors = {}), reset });
        async function submit() {
            const respond = new Promise((resolve) => (window.kitRespond = resolve));
            window.kitSubmitted = (window.kitSubmitted ?? 0) + 1;
            form.processing = true;
            fire('start', { visit: {} });
            props.onStart?.();
            const response = await respond;
            if ((response.status ?? 200) >= 400) {
                const handled = { status: response.status, headers: response.headers ?? {}, data: response.data ?? '' };
                if (props.onHttpException?.(handled) !== false) {
                    const overlay = document.createElement('div');
                    overlay.append(document.createElement('iframe'));
                    document.body.append(overlay);
                }
            } else {
                page.props = { ...page.props, ...(response.props ?? {}) };
                const errors = response.errors ?? {};
                if (Object.keys(errors).length > 0) {
                    form.errors = errors;
                    props.onError?.(errors);
                    maybeReset(props.resetOnError);
                } else {
                    form.errors = {};
                    props.onSuccess?.(page);
                    maybeReset(props.resetOnSuccess);
                }
            }
            form.processing = false;
            fire('finish', { visit: {} });
            props.onFinish?.();
        }
        expose(form);
        provide(FormContext, form);
        return () => h('form', { ...attrs, ref: element, action: props.action, method: props.method, onSubmit: (event) => (event.preventDefault(), submit()) }, slots.default?.(form));
    },
});
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
export const useFormContext = () => inject(FormContext, null);
export const useForm = (data = {}) => reactive({ ...data, errors: {}, processing: false, submit() {}, post() {}, reset() {} });
// Inertia's HTTP client: offline, unless a page installs `window.kitHttpClient` (?workbench-reorder does, with v3's XHR client's shape: `data` is the raw text).
export const http = { getClient: () => window.kitHttpClient ?? { request: () => Promise.reject(new Error('offline')) } };

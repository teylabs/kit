import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';

// session-ui's guest pages on the real @inertiajs/vue3 (auth.html?auth=Login and so on; vite.auth.config.js), so <Form>'s props, options and Inertia's own
// error modal behave as in an app. The test answers each request with Playwright's page.route: an Inertia response (X-Inertia, with `errors`), or a plain
// one (Laravel's 429 HTML from `throttle:`). `kitUi.form` is the kit's `ui` lang copy as the server shares it; `status` (?status=) a view model's flashed status.
const pages = import.meta.glob('@/pages/Auth/*/Page.vue', { eager: true }) as Record<string, { default: object }>;
const query = new URLSearchParams(window.location.search);
const name = query.get('auth') ?? 'Login';
const urls: Record<string, string> = { Login: '/login', ForgotPassword: '/forgot-password', ResetPassword: '/reset-password/token', ConfirmPassword: '/user/confirm-password', VerifyEmail: '/email/verify' };
const props = {
    title: name,
    layout: { title: name },
    status: query.get('status'),
    email: name === 'ResetPassword' ? 'ada@example.com' : null,
    token: 'token',
    localLogin: null,
    cancel: '/',
    errors: {},
    kitUi: { form: { throttled: 'Too many requests. Try again in a moment.', throttled_retry: 'Too many requests. Try again in :seconds seconds.' } },
};
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: `Auth/${name}/Page`, props, url: urls[name] ?? '/', version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: (component: string) => pages[Object.keys(pages).find((path) => path.endsWith(`/${component.split('/')[1]}/Page.vue`)) as string],
    setup: ({ el, App, props: appProps, plugin }) => createApp({ render: () => h(App, appProps) }).use(plugin).mount(el),
    progress: false,
});

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import ErrorPage from '@/pages/Workspace/Error/Page.vue';
import Layout from '@/pages/Workspace/Layout.vue';
import GuestShell from '@/pages/Workspace/Partials/GuestShell.vue';
import PublicShell from '@/pages/Workspace/Partials/PublicShell.vue';

// workspace-ui's error page (error.html, built with the auth pages by vite.auth.config.js) on the real @inertiajs/vue3, in each shell a host's layout
// callback may pick: `?shell=public` (a guest's error, the PublicShell), `guest` (the GuestShell with no title), `guest-titled` (the GuestShell given the
// page's title, as a host may pass it) and `app` (the signed-in Workspace Layout, whose <Page> draws the layout's title). Invented names only.
const query = new URLSearchParams(window.location.search);
const kind = query.get('shell') ?? 'public';
const title = 'Page not found';
const product = { name: 'Example Product', url: '/', logomark: null, logomark_dark: null, wordmark: null, wordmark_dark: null, lockup: false };
const app = {
    kind: 'app',
    account: { label: 'Account', url: '/account', current: false },
    product,
    workspace: { id: 1, name: 'Acme Workshop', url: '/@acme', settings: null, all: null, leave: null },
    switcher: { current: { id: 1, name: 'Acme Workshop' }, choices: [{ id: 1, name: 'Acme Workshop', url: '/@acme', current: true }] },
    navigation: [{ label: 'Overview', url: '/@acme', current: false, group: 'main' }],
    user: { name: 'Ada Example', email: 'ada@example.test', initials: 'AE', avatar_url: null, avatar_url_2x: null, items: [] },
    links: { home: '/', switcher: '/workspaces', sign_in: '/login', sign_out: '/logout' },
    footer: { identity: 'Example Product', credit: '', links: [] },
    subNavigation: null,
    search: null,
};
const shell = kind === 'app' ? app : { kind: 'guest', product, footer: { identity: 'Example Product', credit: '', links: [] } };
const props = { errors: {}, shell, layout: { documentTitle: title, title, breadcrumbs: [] }, title, status: 404, message: 'This request is unavailable. Your access or the resource may have changed.', recovery_url: '/me/workspaces', recovery_label: 'Return to your workspaces' };
const wrap = (page: object) => (kind === 'app' ? h(Layout, { shell: app }, () => page) : kind === 'public' ? h(PublicShell, { signInUrl: '/login' }, () => page) : h(GuestShell, { title: kind === 'guest-titled' ? title : undefined }, () => page));
createInertiaApp({
    page: { component: 'Workspace/Error/Page', props, url: '/invitations/not.a.token', version: null, flash: {}, clearHistory: false, encryptHistory: false } as never,
    resolve: () => ({ ...ErrorPage, layout: (_h: unknown, page: object) => wrap(page) }),
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
});

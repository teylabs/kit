import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import AppearanceToggle from '@/components/Kit/Appearance/AppearanceToggle.vue';
import Layout from '@/pages/Workspace/Layout.vue';

// The signed-in shell's bar (shell.html, built with the auth pages by vite.auth.config.js) on the real @inertiajs/vue3: the Workspace Layout with ten
// top-level pages, global search, the appearance toggle in `actions` and the account menu, so run.mjs checks that the pages give way before the controls do.
// `?current=N` marks page N current (default the first). Invented names only.
const query = new URLSearchParams(window.location.search);
const labels = ['Overview', 'Projects', 'Schedules', 'Contacts', 'Invoices', 'Documents', 'Inventory', 'Reports', 'Automations', 'Integrations'];
const current = Number(query.get('current') ?? 0);
const navigation = labels.map((label, index) => ({ label, url: `/@acme/${label.toLowerCase()}`, current: index === current, group: 'main' }));
const shell = {
    kind: 'app',
    account: { label: 'Account', url: '/account', current: false },
    product: { name: 'Example Product', url: '/', logomark: null, logomark_dark: null, wordmark: null, wordmark_dark: null, lockup: false },
    workspace: { id: 1, name: 'Acme Workshop', url: '/@acme', settings: null, all: null, leave: null },
    switcher: { current: { id: 1, name: 'Acme Workshop' }, choices: [{ id: 1, name: 'Acme Workshop', url: '/@acme', current: true }] },
    navigation,
    user: { name: 'Ada Example', email: 'ada@example.test', initials: 'AE', avatar_url: null, avatar_url_2x: null, items: [] },
    links: { home: '/', switcher: '/workspaces', sign_in: '/login', sign_out: '/logout' },
    footer: { identity: 'Example Product', credit: '', links: [] },
    subNavigation: null,
    search: { url: '/@acme/search', label: 'Search', placeholder: 'Search…' },
};
const Page = { render: () => h(Layout, { shell }, { actions: () => h(AppearanceToggle), default: () => h('h1', { class: 'p-8 text-xl font-semibold' }, 'Overview') }) };
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Shell', props: { errors: {}, layout: { documentTitle: 'Shell' } }, url: navigation[current].url, version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: () => Page,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
});

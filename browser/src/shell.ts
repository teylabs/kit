import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, reactive } from 'vue';
import './app.css';
import AppearanceToggle from '@/components/Kit/Appearance/AppearanceToggle.vue';
import Layout from '@/pages/Workspace/Layout.vue';

// The signed-in shell's bar (shell.html, built with the auth pages by vite.auth.config.js) on the real @inertiajs/vue3: the Workspace Layout with ten
// top-level pages, global search, the appearance toggle in `actions` and the account menu, so run.mjs checks that the pages give way before the controls do.
// `?current=N` marks page N current (default the first); `?pages=four` gives the bar four pages instead, which fit at 1024 beside search. Invented names only.
const query = new URLSearchParams(window.location.search);
const ten = ['Overview', 'Projects', 'Schedules', 'Contacts', 'Invoices', 'Documents', 'Inventory', 'Reports', 'Automations', 'Integrations'];
const labels = query.get('pages') === 'four' ? ['Dashboard', 'Assignments', 'Notes', 'Settings'] : ten;
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
// `?bar`: the app's own search in the `bar` slot (a 13rem field) and a second icon button among the actions, in place of the kit's search.
const ownBar = query.has('bar');
if (ownBar) Object.assign(shell, { search: null });
const slots = {
    actions: () => (ownBar ? [h(AppearanceToggle), h('button', { type: 'button', class: 'size-10 rounded-md', 'aria-label': 'Notifications' })] : h(AppearanceToggle)),
    ...(ownBar ? { bar: () => h('div', { class: 'min-w-0', 'data-global-search': '' }, h('div', { class: 'relative w-52' }, h('input', { type: 'search', 'aria-label': 'Search', placeholder: 'Search…', class: 'h-9 w-full rounded-md border px-3 text-sm' }))) } : {}),
    default: () => h('h1', { class: 'p-8 text-xl font-semibold' }, 'Overview'),
};
// `?late`: the bar mounts with no navigation and window.kitShowNavigation() hands it the pages afterwards.
const state = reactive(query.has('late') ? { ...shell, navigation: [] as typeof navigation } : shell);
(window as unknown as { kitShowNavigation: () => void }).kitShowNavigation = () => { state.navigation = navigation; };
const Page = { render: () => h(Layout, { shell: state }, slots) };
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Shell', props: { errors: {}, layout: { documentTitle: 'Shell' } }, url: navigation[current].url, version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: () => Page,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
});

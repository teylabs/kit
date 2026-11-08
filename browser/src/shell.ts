import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import AppearanceToggle from '@/components/Kit/Appearance/AppearanceToggle.vue';
import Page from '@/components/Kit/Layout/Page.vue';
import PageTabs from '@/components/Kit/Layout/PageTabs.vue';
import Layout from '@/pages/Workspace/Layout.vue';

// The signed-in shell's bar (shell.html, built with the auth pages by vite.auth.config.js) on the real @inertiajs/vue3: the Workspace Layout with ten
// top-level pages, global search, the appearance toggle in `actions` and the account menu, so run.mjs checks that the pages give way before the controls do.
// `?current=N` marks page N current (default the first). Invented names only.
// `?module=N`: a module's page N (0 is its top page) in a <Page>, with the trail and `layout.navigation` the server would send (the module's sibling pages);
// `&record`: a record page whose frame draws its own PageTabs from `layout.navigation`; `&strip`: the Layout's deprecated `subNav="strip"`.
const query = new URLSearchParams(window.location.search);
const labels = ['Overview', 'Projects', 'Schedules', 'Contacts', 'Invoices', 'Documents', 'Inventory', 'Reports', 'Automations', 'Integrations'];
const module = query.get('module');
const current = module !== null ? 1 : Number(query.get('current') ?? 0);
const navigation = labels.map((label, index) => ({ label, url: `/@acme/${label.toLowerCase()}`, current: index === current, group: 'main' }));
const moduleLabels = ['All projects', 'Timelines', 'Budgets', 'Milestones', 'Templates', 'Archived projects', 'Project settings'];
const moduleSuffixes = ['', '/timelines', '/budgets', '/milestones', '/templates', '/archived', '/settings'];
const modulePage = Number(module ?? 0);
const record = query.has('record');
const items = record
    ? ['Summary', 'Files', 'History'].map((label, index) => ({ label, url: `/@acme/projects/p-17${index ? `/${label.toLowerCase()}` : ''}`, current: index === 0 }))
    : moduleLabels.map((label, index) => ({ label, url: `/@acme/projects${moduleSuffixes[index]}`, current: index === modulePage }));
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
    subNavigation: module !== null && !record ? { label: 'Projects', items } : null,
    search: { url: '/@acme/search', label: 'Search', placeholder: 'Search…' },
};
const title = record ? 'Harbour refit' : moduleLabels[modulePage];
const breadcrumbs = [
    { label: 'Acme Workshop', url: '/@acme', home: true, current: false },
    ...(record || modulePage > 0 ? [{ label: 'Projects', url: '/@acme/projects', home: false, current: false }] : []),
    { label: record ? title : modulePage === 0 ? 'Projects' : title, url: record ? items[0].url : items[modulePage].url, home: false, current: true },
];
const content = () => h('p', { class: 'text-sm' }, 'Content');
const body = module === null
    ? () => h('h1', { class: 'p-8 text-xl font-semibold' }, 'Overview')
    : record
      ? () => h(Page, { full: true, title }, { default: () => [h(PageTabs, { tabs: items.map((item) => ({ href: item.url, label: item.label, current: item.current })), label: 'Project sections', 'data-record-tabs': '' }), content()] })
      : () => h(Page, { full: true, title }, { default: content });
const Shown = { render: () => h(Layout, { shell, subNav: query.has('strip') ? 'strip' : undefined }, { actions: () => h(AppearanceToggle), default: body }) };
const layout = module === null ? { documentTitle: 'Shell' } : { documentTitle: title, title, breadcrumbs, navigation: [{ label: record ? null : 'Projects', items }], navigationHeader: null };
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Shell', props: { errors: {}, layout }, url: module === null ? navigation[current].url : items.find((item) => item.current)!.url, version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: () => Shown,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
});

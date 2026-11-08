import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import WidgetGrid from '@/pages/Workspace/Dashboard/Partials/WidgetGrid.vue';

// The dashboard's widget grid with every kit widget on (dashboard.html, built with the auth pages by vite.auth.config.js), on the real @inertiajs/vue3, so the
// People widget's ScrollList reads its list prop as in an app. `?dark` paints the dark tokens. Invented people only.
if (new URLSearchParams(window.location.search).has('dark')) document.documentElement.classList.add('dark');
const frame = (key: string, title: string, component: string, region: 'main' | 'aside') => ({ key, title, component, kind: region === 'main' ? 'feed' : 'status', region, span: { md: 6, lg: 6 }, state: 'ready', empty: null, error: null, actions: [] as object[] });
const person = (id: string, title: string, description: string) => ({ id, title, url: null, description, meta: [], badges: [], variant: null, group: null, actions: [] });
const widgets = [
    { ...frame('kit.you', 'You', 'you', 'aside'), person: { name: 'Ada Example', email: 'ada@example.test', initials: 'AE', avatar: null, role: 'Owner', workspace: 'Acme' } },
    { ...frame('kit.activity', 'Recent activity', 'activity', 'main'), items: [{ id: 'a1', kind: 'activity', headline: { html: '<strong>Ada Example</strong> · Settings updated' }, occurredAt: '2026-11-06T10:00:00Z', timestamp: null, timestampTitle: null, intent: null, meta: [], count: null, truncated: false, children: [], day: null }] },
    { ...frame('kit.people', 'People', 'people', 'aside'), list: 'people', actions: [{ label: 'Manage people', url: '/settings/people', method: 'get', key: 'manage' }] },
];
const people = { key: 'people', title: 'People', state: 'ready', data: [person('1', 'Ada Example', 'Owner'), person('2', 'Ben Sample', 'Member')], empty: null, error: null };
const Page = { props: ['widgets'], render(this: { widgets: object[] }) { return h(WidgetGrid, { widgets: this.widgets }); } };
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Dashboard', props: { errors: {}, widgets, people }, url: '/@acme', scrollProps: { people: { pageName: 'people', previousPage: null, nextPage: null, currentPage: 1, reset: false } }, version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: () => Page,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
});

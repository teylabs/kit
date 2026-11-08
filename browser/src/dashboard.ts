import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import WidgetGrid from '@/pages/Workspace/Dashboard/Partials/WidgetGrid.vue';

// The dashboard's widget grid with every kit widget on (dashboard.html, built with the auth pages by vite.auth.config.js), on the real @inertiajs/vue3, so the
// People widget's ScrollList reads its list prop as in an app. `?dark` paints the dark tokens; `?setup` adds the You card's personal steps and the "Set up this
// workspace" card; `?solo` sends the setup card alone; `?wide` sets the wide aside. Invented people only.
const query = new URLSearchParams(window.location.search);
if (query.has('dark')) document.documentElement.classList.add('dark');
const frame = (key: string, title: string, component: string, region: 'main' | 'aside') => ({ key, title, component, kind: region === 'main' ? 'feed' : 'status', region, span: { md: 6, lg: 6 }, state: 'ready', empty: null, error: null, actions: [] as object[] });
const person = (id: string, title: string, description: string) => ({ id, title, url: null, description, meta: [], badges: [], variant: null, group: null, actions: [] });
const step = (key: string, label: string, state: string, detail: string) => ({ key, label, state, detail, reason: null, required: false, action: state === 'done' ? null : { label: 'Set up', url: `/welcome/${key}`, name: key } });
const setup = { ...frame('kit.setup', 'Set up this workspace', 'setup', 'aside'), kind: 'setup', checklist: [step('workspace-timezone', 'Workspace timezone', 'pending', 'Confirm the timezone this workspace works in'), step('branding', 'Workspace branding', 'skipped', 'Add the logo and colours')] };
const all = [
    { ...frame('kit.you', 'You', 'you', 'aside'), person: { name: 'Ada Example', email: 'ada@example.test', initials: 'AE', avatar: null, role: 'Owner', workspace: 'Acme' }, checklist: query.has('setup') ? [step('timezone', 'Your timezone', 'done', 'Confirm the timezone your dates and times show in'), step('photo', 'Your photo', 'pending', 'Add a photo so teammates know who you are')] : [] },
    { ...frame('kit.activity', 'Recent activity', 'activity', 'main'), items: [{ id: 'a1', kind: 'activity', headline: { html: '<strong>Ada Example</strong> · Settings updated' }, occurredAt: '2026-11-06T10:00:00Z', timestamp: null, timestampTitle: null, intent: null, meta: [], count: null, truncated: false, children: [], day: null }] },
    { ...frame('kit.people', 'People', 'people', 'aside'), list: 'people', actions: [{ label: 'Manage people', url: '/settings/people', method: 'get', key: 'manage' }] },
    ...(query.has('setup') ? [setup] : []),
];
const widgets = query.has('solo') ? [setup] : all;
const people = { key: 'people', title: 'People', state: 'ready', data: [person('1', 'Ada Example', 'Owner'), person('2', 'Ben Sample', 'Member')], empty: null, error: null };
const Page = { props: ['widgets'], render(this: { widgets: object[] }) { return h(WidgetGrid, { widgets: this.widgets, aside: query.has('wide') ? 'wide' : 'narrow' }); } };
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Dashboard', props: { errors: {}, widgets, people }, url: '/@acme', scrollProps: { people: { pageName: 'people', previousPage: null, nextPage: null, currentPage: 1, reset: false } }, version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: () => Page,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
});

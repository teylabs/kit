import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import DashboardPage from '@/pages/Workspace/Dashboard/Page.vue';
import WidgetGrid from '@/pages/Workspace/Dashboard/Partials/WidgetGrid.vue';

// The dashboard's widget grid with every kit widget on (dashboard.html, built with the auth pages by vite.auth.config.js), on the real @inertiajs/vue3, so the
// People widget's ScrollList reads its list prop as in an app. `?dark` paints the dark tokens; `?setup` adds the You card's personal steps and the "Set up this
// workspace" card; `?solo` sends the setup card alone; `?wide` sets the wide aside; `&done` (with `?setup`) marks every personal step done; `?sorted` adds an
// "Ongoing items" card first in main with a Sort by control ("Last touched" by default), whose rows `window.kitOngoing(order)` builds; `?tall` gives Recent activity a `loadingHeight` of 18rem. Invented people only.
const query = new URLSearchParams(window.location.search);
if (query.has('dark')) document.documentElement.classList.add('dark');
const frame = (key: string, title: string, component: string, region: 'main' | 'aside') => ({ key, title, component, kind: region === 'main' ? 'feed' : 'status', region, span: { md: 6, lg: 6 }, state: 'ready', empty: null, error: null, actions: [] as object[] });
const person = (id: string, title: string, description: string) => ({ id, title, url: null, description, meta: [], badges: [], variant: null, group: null, actions: [] });
const step = (key: string, label: string, state: string, detail: string) => ({ key, label, state, detail, reason: null, required: false, action: state === 'done' ? null : { label: 'Set up', url: `/welcome/${key}`, name: key } });
const setup = { ...frame('kit.setup', 'Set up this workspace', 'setup', 'aside'), kind: 'setup', checklist: [step('workspace-timezone', 'Workspace timezone', 'pending', 'Confirm the timezone this workspace works in'), step('branding', 'Workspace branding', 'skipped', 'Add the logo and colours')] };
const all = [
    { ...frame('kit.you', 'You', 'you', 'aside'), actions: [{ label: 'Edit', url: '/me/profile', method: 'get', key: 'profile' }], person: { name: 'Ada Example', email: 'ada@example.test', initials: 'AE', avatar: null, role: 'Owner', workspace: 'Acme', timezone: 'America/New_York' }, checklist: query.has('setup') ? [step('timezone', 'Your timezone', 'done', 'Confirm the timezone your dates and times show in'), step('photo', 'Your photo', query.has('done') ? 'done' : 'pending', 'Add a photo so teammates know who you are')] : [] },
    { ...frame('kit.activity', 'Recent activity', 'activity', 'main'), ...(query.has('tall') ? { loadingHeight: '18rem' } : {}), items: [{ id: 'a1', kind: 'activity', headline: { html: '<strong>Ada Example</strong> · Settings updated' }, occurredAt: '2026-11-06T10:00:00Z', timestamp: null, timestampTitle: null, intent: null, meta: [], count: null, truncated: false, children: [], day: null }] },
    { ...frame('kit.people', 'People', 'people', 'aside'), list: 'people', actions: [{ label: 'Manage people', url: '/settings/people', method: 'get', key: 'manage' }] },
    ...(query.has('setup') ? [setup] : []),
];
const ongoingRows: Record<string, string[]> = { touched: ['Roof survey', 'Lease renewal', 'Boiler check'], opened: ['Boiler check', 'Roof survey', 'Lease renewal'], due: ['Lease renewal', 'Boiler check', 'Roof survey'] };
const ongoing = (order: string) => ({
    ...frame('app.ongoing', 'Ongoing items', 'activity', 'main'), kind: 'task',
    items: ongoingRows[order].map((title, i) => ({ id: `${order}-${i}`, kind: 'activity', headline: { text: title }, occurredAt: '2026-11-06T10:00:00Z', timestamp: null, timestampTitle: null, intent: null, meta: [], count: null, truncated: false, children: [], day: null })),
    sortBy: { label: 'Sort by', options: [{ key: 'touched', label: 'Last touched' }, { key: 'opened', label: 'Newest' }, { key: 'due', label: 'Due soonest' }], current: order, url: '/@acme/widgets/app.ongoing/sort', method: 'put', reload: ['widgets'] },
});
(window as unknown as { kitOngoing: unknown }).kitOngoing = ongoing;
const widgets = query.has('solo') ? [setup] : query.has('sorted') ? [ongoing('touched'), ...all] : all;
const people = { key: 'people', title: 'People', state: 'ready', data: [person('1', 'Ada Example', 'Owner'), person('2', 'Ben Sample', 'Member')], empty: null, error: null };
// `?legacy`: the whole dashboard Page with an app's deprecated `welcome` card (until 0.16), as an app on the pre-0.15.41 WelcomeCardData sends it.
const welcome = { title: 'Welcome to Acme, Ada.', body: 'Your workspace is ready.', dismiss: { label: 'Dismiss welcome', url: '/me/welcome/1', method: 'delete' }, checklist: [{ id: 'timezone', label: 'Your timezone', hint: 'Confirm your timezone', url: '/welcome/timezone', state: 'done' }, { id: 'photo', label: 'Your photo', hint: 'Add a photo', url: '/welcome/photo', state: 'skipped' }], done: 1, total: 2 };
const Page = query.has('legacy')
    ? { props: ['widgets'], render(this: { widgets: object[] }) { return h(DashboardPage as never, { title: 'Dashboard', workspace: 'Acme', next_label: 'Open settings', next_url: '/settings', greeting: 'Good morning, Ada', welcome, widgets: this.widgets, aside: 'narrow' }); } }
    : { props: ['widgets'], render(this: { widgets: object[] }) { return h(WidgetGrid, { widgets: this.widgets, aside: query.has('wide') ? 'wide' : 'narrow' }); } };
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Dashboard', props: { errors: {}, widgets, people }, url: '/@acme', scrollProps: { people: { pageName: 'people', previousPage: null, nextPage: null, currentPage: 1, reset: false } }, version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: () => Page,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
});

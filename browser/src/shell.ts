import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, reactive, ref } from 'vue';
import './app.css';
import AppearanceToggle from '@/components/Kit/Appearance/AppearanceToggle.vue';
import Page from '@/components/Kit/Layout/Page.vue';
import PageTabs from '@/components/Kit/Layout/PageTabs.vue';
import DataTable from '@/components/Kit/Table/DataTable.vue';
import { focusFirstInvalid } from '@/components/Kit/Form/formFrame';
import Layout from '@/pages/Workspace/Layout.vue';
import inter400 from '../../resources/fonts/files/inter-latin-400-normal.woff2?url';
import inter500 from '../../resources/fonts/files/inter-latin-500-normal.woff2?url';
import inter600 from '../../resources/fonts/files/inter-latin-600-normal.woff2?url';

// The signed-in shell's bar (shell.html, built with the auth pages by vite.auth.config.js) on the real @inertiajs/vue3: the Workspace Layout with ten
// top-level pages, global search, the appearance toggle in `actions` and the account menu, so run.mjs checks that the pages give way before the controls do.
// `?current=N` marks page N current (default the first); `?pages=four` gives the bar four pages instead, which fit at 1024 beside search. Invented names only.
// `?module=N`: a module's page N (0 is its top page) in a <Page>, with the trail and `layout.navigation` the server would send (the module's sibling pages);
// `&no-heading`: the page hides its heading (`:heading="false"`) and has a header action, which joins the tab row; `&with-actions`: the same with its heading.
// `&record`: a record page whose frame draws its own PageTabs from `layout.navigation`; `&strip`: the Layout's `subNav="strip"`; `&module-pills`: the module
// opts into pills (Registry::moduleVariant(), `variant: 'secondary'` on its group).
// window.kitRecordTabs(false) unmounts the record's PageTabs (a `v-if` turned off) and (true) brings it back.
const query = new URLSearchParams(window.location.search);
// `?dark` paints the dark tokens.
if (query.has('dark')) document.documentElement.classList.add('dark');
// `?blur=<length>` and `&subblur=<length>` set the host's `--shell-bar-blur` and `--shell-subbar-blur` on the root before the shell mounts.
// `?bar=<colour>` sets the host's --shell-bar (a dark bar).
if (query.has('bar')) document.documentElement.style.setProperty('--shell-bar', query.get('bar') ?? '');
if (query.has('blur')) document.documentElement.style.setProperty('--shell-bar-blur', query.get('blur') ?? '');
if (query.has('subblur')) document.documentElement.style.setProperty('--shell-subbar-blur', query.get('subblur') ?? '');
const ten = ['Overview', 'Projects', 'Schedules', 'Contacts', 'Invoices', 'Documents', 'Inventory', 'Reports', 'Automations', 'Integrations'];
const labels = query.get('pages') === 'four' ? ['Dashboard', 'Assignments', 'Notes', 'Settings'] : ten;
const module = query.get('module');
// `?switch`: two more workspaces in the switcher, whose pages it loads from `landings` (the test answers /me/switcher) when it opens, or on hover or focus.
const switching = query.has('switch');
// A long workspace name (?name=…) for the bar's give-way check.
const workspaceName = query.get('name') ?? 'Acme Workshop';
// `&breakout` (with `module`): Billing, a module with no primary link, reached from a landing (Admin): its own row, the banded "Back to Admin" strip and,
// as kit.breakout 'with-trail', the app's whole trail too. No primary link is current.
const breakout = query.has('breakout');
const current = breakout ? -1 : query.has('linked') ? 1 : module !== null ? 1 : Number(query.get('current') ?? 0);
const navigation = labels.map((label, index) => ({ label, url: `/@acme/${label.toLowerCase()}`, current: index === current, group: 'main' }));
// `&billing`: the module's seven pages are billing ones instead, the row that showed a vertical scrollbar on a wide window.
const moduleLabels = query.has('billing')
    ? ['Invoices', 'Subscriptions', 'Allowances', 'Plans', 'Add-ons', 'Passes', 'Billing entities']
    : ['All projects', 'Timelines', 'Budgets', 'Milestones', 'Templates', 'Archived projects', 'Project settings'];
const moduleSuffixes = ['', '/timelines', '/budgets', '/milestones', '/templates', '/archived', '/settings'];
const modulePage = Number(module ?? 0);
const record = query.has('record');
const items = record
    ? ['Summary', 'Files', 'History'].map((label, index) => ({ label, url: `/@acme/projects/p-17${index ? `/${label.toLowerCase()}` : ''}`, current: index === 0 }))
    : moduleLabels.map((label, index) => ({ label, url: `/@acme/projects${moduleSuffixes[index]}`, current: index === modulePage }));
// `&nested` (with `module`): a section (Admin) whose current tab is a nested module (Billing), so its pages are a second row; `&mixed`: a section (Front desk)
// of plain pages and a nested module (Sales) with a plain page current, one row. Both mark the section's primary link current.
// `&pills`: Front desk with its nested Sales module current: Sales' own pages (Sales, Café menu) as the pill row under the tabs.
const twoLevel = query.has('nested') ? 'nested' : query.has('mixed') ? 'mixed' : query.has('pills') ? 'pills' : null;
if (twoLevel) {
    const tabs = twoLevel === 'nested' ? [['Overview', 'admin'], ['Billing', 'billing'], ['Users', 'admin/users'], ['Settings', 'settings']] : [['Today', 'frontdesk'], ['Sales', 'sales'], ['Check-in', 'frontdesk/checkin'], ['Till', 'frontdesk/till']];
    const at = twoLevel === 'nested' || twoLevel === 'pills' ? 1 : 2;
    // `&pills` carries module-page icons on some tabs and pills (Today, Check-in; Café menu) and none on the rest, as the server sends them.
    const tabIcons: Record<string, string> = twoLevel === 'pills' ? { frontdesk: 'user-check', 'frontdesk/checkin': 'badge-check' } : {};
    items.splice(0, items.length, ...tabs.map(([label, suffix], index) => ({ label, url: `/@acme/${suffix}`, current: index === at, ...(tabIcons[suffix] ? { icon: tabIcons[suffix] } : {}) })));
    navigation[1].label = twoLevel === 'nested' ? 'Admin' : 'Front desk';
}
// `&home` (with `module=N`): Hubcommons's Home row, five pages each with an icon (Hub, Front desk, Guests, Mail, Sales), page N current; with `&module-pills`
// it is a pill row wider than a phone.
if (query.has('home')) {
    const home: [string, string, string][] = [['Hub', '', 'home'], ['Front desk', 'frontdesk', 'user-check'], ['Guests', 'guests', 'users'], ['Mail', 'mail', 'mail'], ['Sales', 'sales', 'receipt']];
    items.splice(0, items.length, ...home.map(([label, suffix, icon], index) => ({ label, url: `/@acme/${suffix}`, current: index === modulePage, icon })));
}
if (breakout) items.splice(0, items.length, ...[['Summary', 'billing'], ['Invoices', 'billing/invoices'], ['Payouts', 'billing/payouts'], ['Plans', 'billing/plans']].map(([label, suffix], index) => ({ label, url: `/@acme/${suffix}`, current: index === 1 })));
const child = twoLevel === 'pills' ? { label: 'Sales', items: [['Sales', 'sales'], ['Café menu', 'sales/cafe-menu']].map(([label, suffix], index) => ({ label, url: `/@acme/${suffix}`, current: index === 1, ...(suffix === 'sales/cafe-menu' ? { icon: 'coffee' } : {}) })) } : twoLevel === 'nested' ? { label: 'Billing', items: [['Summary', 'billing'], ['Invoices', 'billing/invoices'], ['Payouts', 'billing/payouts'], ['Plans', 'billing/plans']].map(([label, suffix], index) => ({ label, url: `/@acme/${suffix}`, current: index === 1 })) } : null;
// `&sidebar`: Billing as HC has it, a sidebar module (nine pages in three groups) with no primary link, as a with-trail breakout (with `&breakout`); with
// `&linked` instead, Billing is the current primary link, for the drawer's grouped list. `&width=<size>`: the module's declared width (layout.width) on a
// page that sets none; `&own=<size>`: the page's own width prop, which wins.
const sidebar = query.has('sidebar');
const sectionLabel = sidebar ? 'Billing' : breakout ? 'Summary' : twoLevel === 'nested' ? 'Admin' : twoLevel === 'mixed' || twoLevel === 'pills' ? 'Front desk' : 'Projects';
const billingGroups: [string, [string, string][]][] = [
    ['Invoicing', [['Invoices', 'billing/invoices'], ['Credit notes', 'billing/credit-notes'], ['Payments', 'billing/payments']]],
    ['Catalogue', [['Plans', 'billing/plans'], ['Add-ons', 'billing/add-ons'], ['Passes', 'billing/passes']]],
    ['Setup', [['Billing entities', 'billing/entities'], ['Tax rates', 'billing/tax'], ['Payment methods', 'billing/methods']]],
];
// Billing's sidebar items carry a SectionIcon name or null (no icon, as the server sends it): Invoices and Plans have one, the rest none.
const billingIcons: Record<string, string> = { 'billing/invoices': 'receipt', 'billing/plans': 'calendar' };
if (sidebar) items.splice(0, items.length, ...billingGroups.flatMap(([group, pages]) => pages.map(([label, suffix]) => ({ label, url: `/@acme/${suffix}`, current: suffix === 'billing/invoices', group, icon: billingIcons[suffix] ?? null }))));
if (sidebar && query.has('linked')) navigation[1].label = 'Billing';
const shell = {
    kind: 'app',
    account: { label: 'Account', url: '/account', current: false },
    product: { name: 'Example Product', url: '/', logomark: null, logomark_dark: null, wordmark: null, wordmark_dark: null, lockup: false },
    workspace: { id: 1, name: workspaceName, url: '/@acme', settings: null, all: null, leave: null },
    switcher: { current: { id: 1, name: workspaceName }, choices: [{ id: 1, name: workspaceName, slug: 'acme', current: true }, ...(switching ? [{ id: 2, name: 'Northwind Farms', slug: 'northwind', current: false }, { id: 3, name: 'Globex Labs', slug: 'globex', current: false }] : [])], landings: '/me/switcher' },
    navigation,
    user: { name: 'Ada Example', email: 'ada@example.test', initials: 'AE', avatar_url: null, avatar_url_2x: null, avatar_color: query.get('avatar-color') ?? null, items: [] },
    links: { home: '/', switcher: '/workspaces', sign_in: '/login', sign_out: '/logout' },
    footer: { identity: 'Example Product', credit: '', links: [] },
    subNavigation: module !== null && !record ? { label: sectionLabel, items, ...(child ? { child } : {}) } : null,
    search: { url: '/@acme/search', label: 'Search', placeholder: 'Search…' } as { url: string; label: string; placeholder: string } | null,
};
const title = breakout || sidebar ? 'Invoices' : twoLevel === 'pills' ? 'Café menu' : record ? 'Harbour refit' : twoLevel === 'nested' ? 'Invoices' : twoLevel === 'mixed' ? 'Check-in' : moduleLabels[modulePage];
const breadcrumbs = breakout
    ? [['Acme Workshop', '/@acme'], ['Admin', '/@acme/admin'], ['Billing', '/@acme/billing'], ['Invoices', '/@acme/billing/invoices']].map(([label, url], index) => ({ label, url, home: index === 0, current: index === 3 }))
    : [
    { label: 'Acme Workshop', url: '/@acme', home: true, current: false },
    ...(record || modulePage > 0 ? [{ label: sectionLabel, url: '/@acme/projects', home: false, current: false }] : []),
    { label: record ? title : modulePage === 0 ? sectionLabel : title, url: record ? items[0].url : items[modulePage].url, home: false, current: true },
];
const recordTabs = ref(true);
(window as unknown as { kitRecordTabs: (shown: boolean) => void }).kitRecordTabs = (shown) => { recordTabs.value = shown; };
// `&top` (with `module`): a long page under the shell's `--shell-top`: a `sticky top-(--shell-top)` row, a #hash target far down (`#far`) and a tall invalid
// field (`#notes`) that window.kitValidate() focuses and scrolls to as a form's 422 does. window.kitTrail(false) draws the trail on the page instead of the bar.
const trail = ref<'bar' | 'page'>('bar');
(window as unknown as { kitTrail: (shown: boolean) => void }).kitTrail = (shown) => { trail.value = shown ? 'bar' : 'page'; };
(window as unknown as { kitValidate: () => Promise<unknown> }).kitValidate = () => focusFirstInvalid(() => [document], { notes: 'Add a note.' });
// `&table` (with `module`): a 60-row DataTable on the page, whose header sticks under the bar as the window scrolls; `&wide` gives it 14 wide columns.
const col = (key: string, label: string) => ({ key, label, sortable: false, hideable: false, hiddenByDefault: false, align: null, srOnlyLabel: false, sortBy: null });
const wideTable = query.has('wide');
const extra = wideTable ? Array.from({ length: 12 }, (_, i) => col(`c${i}`, `Long column heading ${i + 1}`)) : [];
const tableData = {
    rows: Array.from({ length: 60 }, (_, i) => ({ id: i + 1, name: `Member ${i + 1}`, email: `member${i + 1}@example.com`, ...Object.fromEntries(extra.map((c) => [c.key, 'Unbroken-long-cell-value-0001'])), actions: [] })),
    columns: [col('name', 'Name'), { ...col('email', 'Email'), hideable: query.has('filters') }, ...extra],
    sort: null, search: null, filters: [], tabs: [], bulkActions: [],
    // `&filters`: two filter selects (Role, Status). On a phone they sit behind one Filters button.
    ...(query.has('filters') ? { choices: [
        { key: 'role', label: 'Role', options: [{ value: 'admin', label: 'Admin' }, { value: 'member', label: 'Member' }] },
        { key: 'status', label: 'Status', options: [{ value: 'active', label: 'Active' }, { value: 'suspended', label: 'Suspended' }] },
    ] } : {}),
    pagination: { page: 1, perPage: 60, total: 60, from: 1, to: 60, lastPage: 1 },
    emptyState: null,
};
const content = query.has('table')
    ? () => [h(DataTable, { table: tableData as never, toolbar: query.has('filters'), rowLabel: 'name', searchLabel: query.has('filters') ? 'Search by name or email' : 'Search' }), h('div', { style: 'height: 1500px' })]
    : query.has('top')
    ? () => [
          h('div', { class: 'sticky top-(--shell-top) z-10 h-10 border-b bg-background text-sm', 'data-under-bar': '' }, 'Pinned under the bar'),
          h('div', { style: 'height: 1500px' }),
          h('textarea', { id: 'notes', name: 'notes', 'aria-invalid': 'true', 'aria-label': 'Notes', rows: 4, class: 'h-32 w-full rounded-md border' }),
          h('div', { style: 'height: 1500px' }),
          h('h2', { id: 'far', class: 'text-lg font-semibold' }, 'Far section'),
          h('div', { style: 'height: 1500px' }),
      ]
    : () => h('p', { class: 'text-sm' }, 'Content');
const body = module === null
    ? () => h('h1', { class: 'p-8 text-xl font-semibold' }, 'Overview')
    : record
      ? () => h(Page, { full: true, title }, { default: () => [recordTabs.value && h(PageTabs, { tabs: items.map((item) => ({ href: item.url, label: item.label, current: item.current })), label: 'Project sections', 'data-record-tabs': '' }), content()] })
      : query.has('no-heading') || query.has('with-actions')
        ? () =>
              h(Page, { full: true, title, heading: !query.has('no-heading') }, {
                  default: content,
                  actions: () => h('button', { type: 'button', class: 'rounded-md border px-3 py-1.5 text-sm', 'data-new-booking': '' }, 'New booking'),
              })
        : () => h(Page, { ...(query.get('own') ? { [query.get('own') as string]: true } : query.get('width') ? {} : { full: true }), title }, { default: content });
// `?bar`: the app's own search in the `bar` slot (a 13rem field) and a second icon button among the actions, in place of the kit's search.
// `?app` (with `pages=four`): an app's real bar, rebuilt: a square tile in the `mark` slot, the field three boxes deep in the slot (a min-w-0 wrapper, the
// 13rem box, a min-w-0 box, the input), the appearance toggle and a bell among the actions, all in Inter (the kit's own font files, so every runner measures
// the same text). Its pages need a few pixels more than the bar leaves them at 1024 unless the search gives way.
const appBar = query.has('app');
const ownBar = query.has('bar') || appBar;
if (appBar) shell.workspace.name = shell.switcher.current.name = shell.switcher.choices[0].name = 'Harbour workspace';
if (ownBar) shell.search = null;
const slots = {
    actions: () => (ownBar ? [h(AppearanceToggle), h('button', { type: 'button', class: 'size-10 rounded-md', 'aria-label': 'Notifications' })] : h(AppearanceToggle)),
    ...(appBar
        ? {
              mark: () => h('img', { src: tile, alt: '', class: 'h-full w-auto' }),
              bar: () => h('div', { class: 'min-w-0', 'data-global-search': '' }, h('div', { class: 'relative w-52 xl:w-64' }, h('div', { class: 'relative min-w-0', 'data-global-search-box': '' }, h('input', { type: 'search', 'aria-label': 'Search', placeholder: 'Search reference, contact…', class: 'h-9 w-full rounded-md border border-current/20 bg-background px-3 py-0 pr-10 text-sm text-foreground', 'data-global-search-input': '' })))),
          }
        : ownBar
          ? { bar: () => h('div', { class: 'min-w-0', 'data-global-search': '' }, h('div', { class: 'relative w-52' }, h('input', { type: 'search', 'aria-label': 'Search', placeholder: 'Search…', class: 'h-9 w-full rounded-md border px-3 text-sm' }))) }
          : {}),
    default: body,
};
// `?late`: the bar mounts with no navigation and window.kitShowNavigation() hands it the pages afterwards.
const state = reactive(query.has('late') ? { ...shell, navigation: [] as typeof navigation } : shell);
(window as unknown as { kitShowNavigation: () => void }).kitShowNavigation = () => { state.navigation = navigation; };
const tile = `data:image/svg+xml,${encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32"><rect width="32" height="32" rx="6" fill="#e0a800"/></svg>')}`;
// The app bar mounts once its font has loaded, so the first measure is of the real text.
const fonts = appBar
    ? Promise.all(([[400, inter400], [500, inter500], [600, inter600]] as const).map(([weight, url]) => { const face = new FontFace('Inter', `url(${url})`, { weight: String(weight) }); document.fonts.add(face); return face.load(); })).then(() => { document.documentElement.style.fontFamily = 'Inter, sans-serif'; })
    : Promise.resolve();
const Shown = { render: () => h(Layout, { shell: state, subNav: query.has('strip') ? 'strip' : undefined, breadcrumbs: trail.value }, slots) };
const layout = module === null ? { documentTitle: 'Shell' } : { documentTitle: title, title, breadcrumbs, navigation: sidebar ? billingGroups.map(([group]) => ({ label: group, items: items.filter((item) => (item as { group?: string }).group === group), nested: false, sidebar: true })) : [{ label: record ? null : sectionLabel, items, ...(query.has('module-pills') ? { variant: 'secondary' } : {}) }, ...(child ? [{ label: child.label, items: child.items, nested: true }] : [])], width: query.get('width'), navigationHeader: breakout ? { label: 'Back to Admin', url: '/@acme/admin', trail: true } : null };
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Shell', props: { errors: {}, layout }, url: module === null ? navigation[current].url : (child?.items.find((item) => item.current) ?? items.find((item) => item.current)!).url, version: null, flash: {}, clearHistory: false, encryptHistory: false };
void fonts.then(() => createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: () => Shown,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
}));

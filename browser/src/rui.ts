import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import Layout from '@/pages/Workspace/Layout.vue';
import GuestShell from '@/pages/Workspace/Partials/GuestShell.vue';
import PublicShell from '@/pages/Workspace/Partials/PublicShell.vue';
import SettingsLayout from '@/pages/Workspace/Settings/Layout.vue';

// The first-deploy pages (rui.html, built with the auth pages by vite.auth.config.js) with invented props, one per `?page=`, in the shell a host's layout
// callback gives it, for the Refactoring UI checks (axe colour-contrast light and dark) and their screenshots; `&dark` paints the dark tokens. Invented names only.
const query = new URLSearchParams(window.location.search);
if (query.has('dark')) document.documentElement.classList.add('dark');
const name = query.get('page') ?? 'error';
const pages = import.meta.glob(['@/pages/Workspace/Error/Page.vue', '@/pages/Provision/NoWorkspace/Page.vue', '@/pages/Workspace/Search/Page.vue', '@/pages/Workspace/Settings/People/Page.vue', '@/pages/Workspace/Account/Security/Page.vue', '@/pages/Auth/Invitation/Show/Page.vue', '@/pages/Auth/Login/Page.vue', '@/pages/Workspace/Dashboard/Page.vue', '@/pages/Workspace/Settings/Placeholder/Page.vue'], { eager: true }) as Record<string, { default: object }>;
const product = { name: 'Example Product', url: '/', logomark: null, logomark_dark: null, wordmark: null, wordmark_dark: null, lockup: false };
const footer = { identity: 'Example Product', credit: '', links: [] };
const app = {
    kind: 'app', account: { label: 'Account', url: '/account', current: false }, product,
    workspace: { id: 1, name: 'Acme Workshop', url: '/@acme', settings: '/@acme/settings', all: null, leave: null },
    switcher: { current: { id: 1, name: 'Acme Workshop' }, choices: [{ id: 1, name: 'Acme Workshop', slug: 'acme', current: true }], landings: '/me/switcher' },
    navigation: [{ label: 'Overview', url: '/@acme', current: true, group: 'main' }, { label: 'Projects', url: '/@acme/projects', current: false, group: 'main' }],
    user: { name: 'Ada Example', email: 'ada@example.test', initials: 'AE', avatar_url: null, avatar_url_2x: null, items: [] },
    links: { home: '/', switcher: '/workspaces', sign_in: '/login', sign_out: '/logout' }, footer, subNavigation: null, search: null,
};
const guest = { kind: 'guest', product, footer, links: { home: '/', sign_in: '/login' } };
const column = (key: string, label: string, more = {}) => ({ key, label, sortable: false, hideable: false, hiddenByDefault: false, align: null, srOnlyLabel: false, sortBy: null, ...more });
const settingsNav = [{ label: null, items: [{ label: 'General', url: '/@acme/settings/general', current: false, icon: 'settings' }, { label: 'People', url: '/@acme/settings/people', current: name === 'people', icon: 'users' }, { label: 'Billing', url: '/@acme/settings/billing', current: name === 'placeholder', icon: 'credit-card', badge: 'Planned' }] }];
const log = { title: 'Security log', description: 'Your recent sign-ins and security changes. Only you can see this.', state: 'empty', empty: { variant: 'first-use', title: 'Nothing yet', description: 'Your sign-ins and security changes show up here.', action: null }, error: null, actions: [], segments: [], sortBy: null, seeAll: null, maxHeight: null };
const session = (id: string, agent: string, current: boolean) => ({ id, agent, current, ip: '203.0.113.7', country: 'Canada', signed_in_at: '2026-11-20T09:00:00+00:00', last_active: '2026-11-26T15:00:00+00:00', remembered: false, url: current ? null : `/me/security/sessions/${id}` });
type Spec = { component: string; shell: 'app' | 'guest' | 'public' | 'focus' | 'settings'; props: Record<string, unknown> };
const specs: Record<string, Spec> = {
    error: { component: 'Workspace/Error/Page', shell: 'public', props: { title: 'Page not found', status: 404, message: 'This request is unavailable. Your access or the resource may have changed.', recovery_url: '/me/workspaces', recovery_label: 'Return to your workspaces' } },
    'error-app': { component: 'Workspace/Error/Page', shell: 'app', props: { title: 'Page not found', status: 404, message: 'This request is unavailable. Your access or the resource may have changed.', recovery_url: '/me/workspaces', recovery_label: 'Return to your workspaces' } },
    'no-workspace': { component: 'Provision/NoWorkspace/Page', shell: 'focus', props: { title: 'You are not in a workspace yet', email: 'ada@example.test', explanation: 'Ask someone to invite you, or create a workspace of your own.', invitations: [{ workspace: 'Northwind Farms', inviter: 'Grace Hopper', expires: '2026-12-04T00:00:00+00:00', message: null }], create: { label: 'Create a workspace', url: '/workspaces/create' }, account: { label: 'Account settings', url: '/me/profile' } } },
    'search-blank': { component: 'Workspace/Search/Page', shell: 'app', props: { title: 'Search', query: '', url: '/@acme/search', groups: [], partial: false } },
    'search-none': { component: 'Workspace/Search/Page', shell: 'app', props: { title: 'Search', query: 'boiler', url: '/@acme/search', groups: [], partial: false } },
    people: { component: 'Workspace/Settings/People/Page', shell: 'settings', props: { title: 'People', tab: 'members', roles: [], invite_link: null, invite: { label: 'Invite member', url: '/@acme/settings/people/invitations', method: 'get' }, table: { rows: [{ id: '1', member: { name: 'Ada Example', email: 'ada@example.test', initials: 'AE', avatar: null, avatar_color: null, is_you: true, invited_as: null }, roles: [{ value: 'owner', label: 'Owner', variant: null }], status: 'active', joined: '2026-10-01T09:00:00+00:00', actions: [] }], columns: [column('member', 'Member'), column('roles', 'Role'), column('joined', 'Joined', { date: 'date' }), column('actions', 'Actions', { align: 'end', srOnlyLabel: true })], sort: null, search: null, filters: [], tabs: [{ key: 'members', label: 'Members', url: '/@acme/settings/people', count: 1, current: true }, { key: 'invitations', label: 'Invitations', url: '/@acme/settings/people?tab=invitations', count: 0, current: false }], bulkActions: [], pagination: { page: 1, perPage: 25, total: 1, from: 1, to: 1, lastPage: 1 }, emptyState: null } } },
    security: { component: 'Workspace/Account/Security/Page', shell: 'app', props: { title: 'Security', notice: null, two_factor: { state: 'on', qr_svg: null, secret_key: null, codes_left: 8, recovery_codes: [] }, passkeys: { options_url: '/user/passkeys/options', store_url: '/user/passkeys', confirm_url: '/user/confirm-password', url: '/me/security/passkeys', items: [] }, sessions: { supported: true, items: [session('a', 'Firefox on macOS', true), session('b', 'Safari on iPhone', false)] }, events: [], log, two_factor_url: '/me/security/two-factor', recovery_codes_url: '/me/security/recovery-codes', sessions_url: '/me/security/sessions' } },
    invitation: { component: 'Auth/Invitation/Show/Page', shell: 'guest', props: { title: 'Join Northwind Farms', state: query.get('state') ?? 'sign_in_required', message: '', invitation: { workspace: 'Northwind Farms', inviter: 'Grace Hopper', role: 'member', email: 'ada@example.com', message: null }, signed_in_as: null, other_email: false, accept_url: '/invitations/token/accept', register_url: '/invitations/token/register', login_url: '/login', workspace_url: null, verify_url: null } },
    login: { component: 'Auth/Login/Page', shell: 'guest', props: { title: 'Sign in', status: null, register: '/register', localLogin: null, passkey: null, kitUi: { form: { throttled: 'Too many requests.', throttled_retry: 'Too many requests.' } } } },
    dashboard: { component: 'Workspace/Dashboard/Page', shell: 'app', props: { title: 'Home', greeting: 'Good morning, Ada', workspace: 'Acme Workshop', widgets: [], aside: 'default', actions: [], next_url: '/@acme/settings/people', next_label: 'Invite your team' } },
    placeholder: { component: 'Workspace/Settings/Placeholder/Page', shell: 'settings', props: { title: 'Billing', description: 'Plans, invoices and payment methods for this workspace.', milestone: 'the billing release', links: [{ label: 'General settings', url: '/@acme/settings/general' }, { label: 'People', url: '/@acme/settings/people' }] } },
};
const spec = specs[name];
const title = String(spec.props.title);
const navigation = spec.shell === 'settings' ? settingsNav : name === 'dashboard' ? [{ label: 'Home', items: [{ label: 'Overview', url: '/@acme', current: true }, { label: 'Activity', url: '/@acme/activity', current: false }, { label: 'Calendar', url: '/@acme/calendar', current: false }] }] : [];
const props = { errors: {}, shell: spec.shell === 'guest' || spec.shell === 'public' ? guest : app, layout: { documentTitle: title, title, breadcrumbs: [], navigation }, ...spec.props };
const page = pages[Object.keys(pages).find((path) => path.endsWith(`/${spec.component}.vue`)) as string];
const wrap = (inner: object) => {
    if (spec.shell === 'public') return h(PublicShell, { signInUrl: '/login' }, () => inner);
    if (spec.shell === 'guest' || spec.shell === 'focus') return h(GuestShell, {}, () => inner);
    if (spec.shell === 'settings') return h(Layout, { shell: app }, () => h(SettingsLayout, null, () => inner));
    return h(Layout, { shell: app }, () => inner);
};
createInertiaApp({
    page: { component: spec.component, props, url: '/rui', version: null, flash: {}, clearHistory: false, encryptHistory: false } as never,
    resolve: () => ({ ...page.default, layout: (_h: unknown, inner: object) => wrap(inner) }),
    setup: ({ el, App, props: appProps, plugin }) => createApp({ render: () => h(App, appProps) }).use(plugin).mount(el),
    progress: false,
});

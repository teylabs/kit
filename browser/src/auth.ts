import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';

// session-ui's guest pages on the real @inertiajs/vue3 (auth.html?auth=Login and so on; vite.auth.config.js), so <Form>'s props, options and Inertia's own
// error modal behave as in an app. The test answers each request with Playwright's page.route: an Inertia response (X-Inertia, with `errors`), or a plain
// one (Laravel's 429 HTML from `throttle:`). `kitUi.form` is the kit's `ui` lang copy as the server shares it; `status` (?status=) a view model's flashed status.
// Register takes ?flow=: `invited` (the address shown as text and posted through `submit`) or `workspace` (shown, not posted), a host's own sign-up through the
// view model; without it, Fortify's open /register. `?register` gives Login its "Create an account" link. Any other page a response names (the invitation page
// a redirect lands on) is a stand-in that shows its `status`.
const pages = import.meta.glob(['@/pages/Auth/*/Page.vue', '@/pages/Mcp/Authorize/Page.vue', '@/pages/Workspace/Account/Security/Page.vue'], { eager: true }) as Record<string, { default: object }>;
const elsewhere = { default: { props: ['status'], render(this: { status: string | null }) { return h('p', { 'data-elsewhere': '' }, this.status ?? ''); } } };
const query = new URLSearchParams(window.location.search);
const name = query.get('auth') ?? 'Login';
const flow = query.get('flow');
const urls: Record<string, string> = { Login: '/login', Register: flow === 'invited' ? '/invitations/token/register' : flow === 'workspace' ? '/invite/token/register' : '/register', ForgotPassword: '/forgot-password', ResetPassword: '/reset-password/token', ConfirmPassword: '/user/confirm-password', VerifyEmail: '/email/verify', 'Workspace/Account/Security': '/me/security' };
const register = {
    heading: flow ? 'Create your account' : 'Create an account',
    lead: flow === 'invited' ? 'You have been invited to join Northwind Farms.' : flow ? null : 'Enter your details below to create your account',
    action: urls.Register,
    submitLabel: flow === 'workspace' ? 'Create account and workspace' : 'Create account',
    emailHint: flow ? 'The invitation was sent to this address.' : null,
    submit: flow === 'invited' ? { email: 'ada@example.com' } : [],
    throttleMessage: flow ? 'Too many attempts with this invitation. Wait a minute, then try again.' : null,
    login: '/login',
};
// ?auth=Mcp/Authorize: mcp's consent page with an invented assistant, two scopes, one workspace (`&two`: a second) and one opt-in grant; `&unchosen`: no
// workspace chosen yet.
const consent = { client: 'Acme Assistant', scopes: [{ key: 'read', label: 'Read your workspace data' }, { key: 'write', label: 'Create and edit records' }], workspaces: [{ id: 1, name: 'Northwind Farms' }, ...(query.has('two') ? [{ id: 2, name: 'Globex Labs' }] : [])], workspace: query.has('unchosen') ? null : 1, approve_url: '/oauth/authorize', deny_url: '/oauth/authorize', auth_token: 't', csrf_token: 'c', redirect_host: 'assistant.example.com', registered: false, grants: [{ scope: 'files', label: 'Files', explains: 'read uploaded files', checked: false }] };
// ?auth=Workspace/Account/Security: Settings › Security with two-factor off, one passkey, and Fortify's passkey endpoints with its password confirmation.
const security = {
    two_factor: { state: 'off', qr_svg: null, secret_key: null, codes_left: 0, recovery_codes: [] },
    sessions: { supported: false, items: [] },
    events: [],
    two_factor_url: '/me/security/two-factor',
    recovery_codes_url: '/me/security/recovery-codes',
    sessions_url: '/me/security/sessions',
    notice: null,
    passkeys: { options_url: '/user/passkeys/options', store_url: '/user/passkeys', confirm_url: '/user/confirm-password', url: '/me/security/passkeys', items: [{ id: 7, name: 'MacBook passkey', created: '2026-10-01T09:00:00+00:00', last_used: null }] },
};
const props = {
    title: name,
    layout: { title: name },
    status: query.get('status'),
    ...(name === 'Register' ? register : {}),
    ...(name === 'Mcp/Authorize' ? consent : {}),
    ...(name === 'Workspace/Account/Security' ? security : {}),
    email: name === 'ResetPassword' || (name === 'Register' && flow) ? 'ada@example.com' : null,
    register: query.has('register') ? '/register' : null,
    token: 'token',
    localLogin: null,
    cancel: '/',
    errors: {},
    kitUi: { form: { throttled: 'Too many requests. Try again in a moment.', throttled_retry: 'Too many requests. Try again in :seconds seconds.' } },
};
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: name.includes('/') ? `${name}/Page` : `Auth/${name}/Page`, props, url: urls[name] ?? '/', version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: (component: string) => pages[Object.keys(pages).find((path) => path.endsWith(`/${component}.vue`)) as string] ?? elsewhere,
    setup: ({ el, App, props: appProps, plugin }) => createApp({ render: () => h(App, appProps) }).use(plugin).mount(el),
    progress: false,
});

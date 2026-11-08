import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import Index from '@/pages/Workspace/Index/Page.vue';

// The workspace list (workspaces.html, built with the auth pages by vite.auth.config.js), on the real @inertiajs/vue3, inside the shell (so the Page draws its heading). `?dark` paints the dark tokens; `?empty`
// has no workspace (the empty state); `?create` sends New workspace. Invented workspaces and people only.
const query = new URLSearchParams(window.location.search);
if (query.has('dark')) document.documentElement.classList.add('dark');
const leave = (slug: string, name: string) => ({ label: 'Leave', url: `/me/workspaces/${slug}/membership`, method: 'delete', confirm: { title: `Leave ${name}?`, description: 'You lose access straight away. An admin can invite you again.', label: 'Leave workspace' } });
const workspaces = query.has('empty') ? [] : [
    { id: 1, name: 'Acme Travel', initials: 'AT', role: 'Admin', members: 12, current: true, leave: leave('acme', 'Acme Travel') },
    { id: 2, name: 'Northwind Farms and Orchards Cooperative of the Lower Valley', initials: 'NF', role: 'Owner', members: 1, current: false, leave: null },
    { id: 3, name: 'Globex Labs', initials: 'GL', role: 'Member', members: 4, current: false, leave: leave('globex', 'Globex Labs') },
];
const invitations = [{ workspace: 'Initech Research', inviter: 'Ben Sample', message: 'Join us for the spring planning.', expires: '2026-11-15T10:00:00Z' }];
const shell = { switcher: { current: null, choices: [{ id: 1, name: 'Acme Travel', url: '/@acme', current: false }, { id: 2, name: 'Northwind Farms and Orchards Cooperative of the Lower Valley', url: '/@northwind', current: false }, { id: 3, name: 'Globex Labs', url: null, current: false }] } };
const create = query.has('create') ? { label: 'New workspace', url: '/me/workspaces/create', method: 'get' } : null;
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Workspace/Index/Page', props: { errors: {}, shell, title: 'Your workspaces', layout: { title: 'Your workspaces' }, workspaces, invitations, create }, url: '/me/workspaces', version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: () => Index,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).provide('kit.shell', true).mount(el),
    progress: false,
});

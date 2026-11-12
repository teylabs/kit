import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, ref } from 'vue';
import './app.css';
import Toaster from '@/components/Kit/Feedback/Toaster.vue';
import General from '@/pages/Workspace/Settings/General/Page.vue';
import Email from '@/pages/Workspace/Settings/Email/Page.vue';
import Profile from '@/pages/Workspace/Account/Profile/Page.vue';
import Password from '@/pages/Workspace/Account/Password/Page.vue';
import Security from '@/pages/Workspace/Account/Security/Page.vue';
import DeleteAccount from '@/pages/Workspace/Account/Delete/Page.vue';
import WelcomeTimezone from '@/pages/Welcome/Timezone/Page.vue';
import WelcomePhoto from '@/pages/Welcome/Photo/Page.vue';
import Notes from '@/pages/Workspace/Notes/Index/Page.vue';
import WorkspaceLayout from '@/pages/Workspace/Layout.vue';
import AccountLayout from '@/pages/Workspace/Account/Layout.vue';

// The settings forms (forms.html, built with the auth pages by vite.auth.config.js) on the real @inertiajs/vue3, with the shell's Toaster, so run.mjs fills
// each form as a person would, presses Save and reads what comes back. The page object is the server's: the first load asks for it with X-Inertia, as a
// reload would, and run.mjs answers that and every visit from its own store, so a saved value shows only if it was posted and kept.
const pages: Record<string, object> = { 'Workspace/Settings/General/Page': General, 'Workspace/Settings/Email/Page': Email };
// The account and session forms (batch 3c): Account › Profile (with the photo), Password, Security (two-factor, recovery codes, passkeys, other sessions) and
// Delete account, the welcome flow's timezone and photo steps, and the notes wall.
Object.assign(pages, {
    'Workspace/Account/Profile/Page': Profile,
    'Workspace/Account/Password/Page': Password,
    'Workspace/Account/Security/Page': Security,
    'Workspace/Account/Delete/Page': DeleteAccount,
    'Welcome/Timezone/Page': WelcomeTimezone,
    'Welcome/Photo/Page': WelcomePhoto,
    'Workspace/Notes/Index/Page': Notes,
});
// `&shell`: the Account pages sit in the app's persistent layouts, as a host's resolver nests them: the Workspace Layout (which owns the document title,
// from the page's `layout.documentTitle`) around the Account folder's Layout.vue (the SettingsFrame sidebar), so moving between sections keeps both mounted.
const shell = {
    kind: 'app',
    account: { label: 'Account', url: '/account', current: true },
    product: { name: 'Example Product', url: '/', logomark: null, logomark_dark: null, wordmark: null, wordmark_dark: null, lockup: false },
    workspace: null,
    switcher: null,
    navigation: [],
    user: { name: 'Robin Vale', email: 'robin@example.test', initials: 'RV', avatar_url: null, avatar_url_2x: null, items: [] },
    links: { home: '/', switcher: '/workspaces', sign_in: '/login', sign_out: '/logout' },
    footer: { identity: 'Example Product', credit: '', links: [] },
    subNavigation: null,
    search: null,
};
// window.kitShellTitle(false) turns the Layout's `documentTitle` off, handing the title back to the pages.
const shellTitle = ref(true);
(window as unknown as { kitShellTitle: (on: boolean) => void }).kitShellTitle = (on) => { shellTitle.value = on; };
const Shell = { render() { return h(WorkspaceLayout, { shell, documentTitle: shellTitle.value }, this.$slots); } };
const layout = new URLSearchParams(window.location.search).has('shell')
    ? (name: string) => (name.startsWith('Workspace/Account/') ? [Shell, AccountLayout] : Shell)
    : undefined;
const lazy = new URLSearchParams(window.location.search).has('lazy');
const response = await fetch(window.location.href, { headers: { 'X-Inertia': 'true', Accept: 'text/html, application/xhtml+xml' } });
const initial = await response.json();
createInertiaApp({
    page: initial,
    // `&lazy`: each page resolves a task later, as a host's code-split import() does.
    resolve: (component: string) => (lazy ? new Promise((done) => setTimeout(() => done(pages[component]))) : pages[component]),
    ...(layout ? { layout } : {}),
    setup: ({ el, App, props, plugin }) => createApp({ render: () => [h(App, props), h(Toaster)] }).use(plugin).provide('kit.shell', true).mount(el),
    progress: false,
});

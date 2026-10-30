<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SettingsFrame } from '@kit/ui';
import Layout from '@/workspace/Layout.vue';

// The kit's shell Layout (?shell-width=normal|settings) in a host's wide shell (one `widthClass` on every page), with the ShellData PageShell sends:
// `&workspaces=2` for two workspaces, `&settings=0` for a viewer who cannot open settings. A settings page puts its content in the SettingsFrame,
// as the Settings folder's Layout.vue does. The account menu has a host `extra` item.
const query = new URLSearchParams(window.location.search);
const two = query.get('workspaces') === '2';
const settings = query.get('settings') !== '0';
const nested = query.get('shell-width') === 'settings';
const action = (label: string, url: string) => ({ label, url, method: 'get', disabled: false, reason: null, confirm: null, key: null, destructive: false, intent: null, group: null, menu: false, icon: null, newTab: null, priority: null, checked: null });
const acme = { id: 1, name: 'Acme Travel', url: '/@acme', current: true };
const choices = two ? [acme, { id: 2, name: 'Northwind Farms', url: '/@northwind', current: false }] : [acme];
const shell = {
    kind: 'app',
    account: { label: 'Your account', url: '/me', current: false },
    product: { name: 'Product', url: '/', logomark: null, logomark_dark: null, wordmark: null, wordmark_dark: null, lockup: false },
    workspace: { id: 1, name: 'Acme Travel', url: '/@acme', settings: settings ? action('Workspace settings', '/@acme/settings') : null, all: action('All workspaces', '/me/workspaces'), leave: null },
    switcher: { current: { id: 1, name: 'Acme Travel' }, choices },
    navigation: [{ label: 'Dashboard', url: '/@acme', current: !nested, group: 'workspace' }, ...(settings ? [{ label: 'Settings', url: '/@acme/settings', current: nested, group: 'workspace' }] : [])],
    user: { name: 'Ada Lovelace', email: 'ada@example.test', initials: 'AL', avatar_url: null, avatar_url_2x: null, items: [{ label: 'Account settings', url: '/me', current: false, group: 'account' }] },
    links: { home: '/', switcher: '/me/workspaces', sign_in: '/login', sign_out: '/logout' },
    footer: null,
    subNavigation: null,
    search: null,
} as never;
const page = usePage<Record<string, unknown>>();
page.props = { shell, layout: { documentTitle: 'Test', breadcrumbs: nested ? [{ label: 'Home', url: '/', home: true }, { label: 'Settings', url: '/@acme/settings' }, { label: 'General', url: null }] : [], navigation: nested ? [{ label: 'Workspace', items: [{ label: 'General', url: '/@acme/settings/general', current: true }, { label: 'People', url: '/@acme/settings/people', current: false }] }] : [] } };
</script>
<template>
    <Layout :shell="shell" width-class="max-w-[160rem]" :toaster="false" :document-title="false">
        <template #extra="{ place }"><a v-if="place === 'menu'" href="/help" class="block px-2 py-1.5 text-sm" data-host-extra>Help</a></template>
        <SettingsFrame v-if="nested"><p data-page-body>General settings</p></SettingsFrame>
        <p v-else data-page-body>Dashboard</p>
    </Layout>
</template>

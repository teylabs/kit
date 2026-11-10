import { createInertiaApp } from '@inertiajs/vue3';
import { withInertiaModal } from '@inertiaui/modal-vue';
import { createApp, h } from 'vue';
import './app.css';
import Toaster from '@/components/Kit/Feedback/Toaster.vue';
import People from '@/pages/Workspace/Settings/People/Page.vue';
import ChangeRole from '@/pages/Workspace/Settings/People/ChangeRole/Page.vue';
import Invite from '@/pages/Workspace/Settings/People/Invite/Page.vue';
import Transfer from '@/pages/Workspace/Settings/People/Transfer/Page.vue';
import Delete from '@/pages/Workspace/Settings/Delete/Page.vue';
import Index from '@/pages/Workspace/Index/Page.vue';

// People's forms (people.html, vite.people.config.js) on the real @inertiajs/vue3 and the real @inertiaui/modal-vue, mounted as an app mounts them
// (withInertiaModal), with the shell's Toaster. Change role, Invite member and Transfer ownership open over People as server-driven modals; Delete workspace is a
// page, and the workspace list is where its redirect lands. As in forms.ts, the first load asks for the page object with X-Inertia and run.mjs answers it, every
// modal visit and every write from its own store, so a saved value shows only if it was posted and kept.
const pages: Record<string, object> = {
    'Workspace/Settings/People/Page': People,
    'Workspace/Settings/People/ChangeRole/Page': ChangeRole,
    'Workspace/Settings/People/Invite/Page': Invite,
    'Workspace/Settings/People/Transfer/Page': Transfer,
    'Workspace/Settings/Delete/Page': Delete,
    'Workspace/Index/Page': Index,
};
const response = await fetch(window.location.href, { headers: { 'X-Inertia': 'true', Accept: 'text/html, application/xhtml+xml' } });
const initial = await response.json();
createInertiaApp({
    page: initial,
    resolve: (component: string) => pages[component],
    setup: ({ el, App, props, plugin }) => {
        const app = createApp({ render: () => [h(App, props), h(Toaster)] });
        withInertiaModal(app as never);
        app.use(plugin).provide('kit.shell', true).mount(el);
    },
    // Inertia's progress bar as a host has it (on by default): @inertiaui/modal-vue starts it on every modal visit.
});

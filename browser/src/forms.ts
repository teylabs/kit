import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import Toaster from '@/components/Kit/Feedback/Toaster.vue';
import General from '@/pages/Workspace/Settings/General/Page.vue';
import Email from '@/pages/Workspace/Settings/Email/Page.vue';

// The settings forms (forms.html, built with the auth pages by vite.auth.config.js) on the real @inertiajs/vue3, with the shell's Toaster, so run.mjs fills
// each form as a person would, presses Save and reads what comes back. The page object is the server's: the first load asks for it with X-Inertia, as a
// reload would, and run.mjs answers that and every visit from its own store, so a saved value shows only if it was posted and kept.
const pages: Record<string, object> = { 'Workspace/Settings/General/Page': General, 'Workspace/Settings/Email/Page': Email };
const response = await fetch(window.location.href, { headers: { 'X-Inertia': 'true', Accept: 'text/html, application/xhtml+xml' } });
const initial = await response.json();
createInertiaApp({
    page: initial,
    resolve: (component: string) => pages[component],
    setup: ({ el, App, props, plugin }) => createApp({ render: () => [h(App, props), h(Toaster)] }).use(plugin).provide('kit.shell', true).mount(el),
    progress: false,
});

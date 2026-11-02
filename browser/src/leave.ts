import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import Edit from './leave/Edit.vue';
import Other from './leave/Other.vue';

// The leave-page guard on the real @inertiajs/vue3 (leave.html, built with the auth pages by vite.auth.config.js), so Back and Forward run through Inertia's
// own history entries and popstate, and a save's redirect and its onSuccess visit are real visits. The app starts on Leave/Other (/other); the test answers
// every other request with Playwright's page.route: GET /edit and /other with an Inertia page, PUT /edit as Laravel's redirect back renders it, and the
// BulkDrop's request and confirm routes.
const pages: Record<string, object> = { 'Leave/Edit': Edit, 'Leave/Other': Other };
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Leave/Other', props: { errors: {} }, url: '/other', version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: (component: string) => pages[component],
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
});

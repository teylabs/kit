import { createInertiaApp } from '@inertiajs/vue3';
import { putConfig, withInertiaModal } from '@inertiaui/modal-vue';
import { createApp, h } from 'vue';
import './app.css';
import SelectModalBase from './SelectModalBase.vue';
import SelectModalPage from './SelectModalPage.vue';
import ReturnFocusPage from './ReturnFocusPage.vue';

// InputSelect beside an Input and in a server-driven modal (select-modal.html, built with People by vite.people.config.js), on the real @inertiajs/vue3 and
// the real @inertiaui/modal-vue, mounted as an app mounts them (withInertiaModal). run.mjs answers the first load, the ModalLink's visit, the remote search and
// the modal's submit with page.route. `?panel=div` turns the package's native <dialog> off (useNativeDialog: false): its panel is then a role=dialog element
// with the package's own focus trap.
if (new URLSearchParams(location.search).get('panel') === 'div') putConfig({ useNativeDialog: false });
const pages: Record<string, object> = { 'Select/Base': SelectModalBase, 'Select/Modal': SelectModalPage, 'Return/Base': ReturnFocusPage };
const response = await fetch(window.location.href, { headers: { 'X-Inertia': 'true', Accept: 'text/html, application/xhtml+xml' } });
createInertiaApp({
    page: await response.json(),
    resolve: (component: string) => pages[component],
    setup: ({ el, App, props, plugin }) => {
        const app = createApp({ render: () => h(App, props) });
        withInertiaModal(app as never);
        app.use(plugin).mount(el);
    },
});

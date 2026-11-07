import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import NoteComposer from '@/pages/Workspace/Notes/Index/Partials/NoteComposer.vue';

// The notes composer (notes.html, built with the auth pages by vite.auth.config.js) on the real @inertiajs/vue3, so its <Form>, transform and reset run as
// in an app. The test answers POST /@acme/notes with Playwright's page.route. `?dark` paints the dark tokens.
if (new URLSearchParams(window.location.search).has('dark')) document.documentElement.classList.add('dark');
const composer = {
    submit: { label: 'Add note', url: '/@acme/notes', method: 'post' },
    placeholder: 'Write a note…',
    colours: [{ key: 'yellow', label: 'Yellow' }, { key: 'blue', label: 'Blue' }, { key: 'green', label: 'Green' }],
    default_colour: 'yellow',
    media: { accept: ['image/png', 'image/jpeg', 'application/pdf'], max_files: 2, max_kb: 1024 },
    subject: null,
    options: [],
    subject_search: null,
};
const Page = { props: ['composer'], render(this: { composer: object }) { return h(NoteComposer, { composer: this.composer }); } };
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Notes', props: { errors: {}, composer }, url: '/@acme/notes', version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: () => Page,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
});

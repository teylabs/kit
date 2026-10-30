import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwind from '@tailwindcss/vite';
import path from 'node:path';

const here = (p) => path.resolve(import.meta.dirname, p);

// The kit's stubs are copied (".stub" dropped) into .kit by run.mjs. Inertia and the shadcn pieces the tested components import are stand-ins (the menus are reka's own), except
// `@/components/ui/button`, the host's real shadcn-vue Button (src/button), so a variant's real classes (primary is bg-primary) paint in the browser.
export default defineConfig({
    plugins: [vue(), tailwind()],
    base: './',
    logLevel: 'warn',
    resolve: {
        alias: [
            { find: /^@inertiajs\/vue3$/, replacement: here('src/inertia.js') },
            { find: /^@inertiaui\/modal-vue$/, replacement: here('src/modal.js') },
            { find: /^@\/components\/ui\/button$/, replacement: here('src/button/index.ts') },
            { find: /^@\/components\/ui\/.*$/, replacement: here('src/ui.js') },
            { find: /^@\/components\/Kit/, replacement: here('.kit/Kit') },
        ],
    },
});

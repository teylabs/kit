import { defineConfig, mergeConfig } from 'vite';
import path from 'node:path';
import base from './vite.config.js';

const here = (p) => path.resolve(import.meta.dirname, p);

// People's forms (people.html, into dist-people): the auth build's real @inertiajs/vue3 and shadcn-vue Input, plus the real @inertiaui/modal-vue in place of the
// stand-in, so Change role, Invite member and Transfer ownership open as the server-driven modals a host renders, over the real People page. Its own build: the
// other pages keep the stand-in modal. Its base is `/`: run.mjs serves people.html at the app's own addresses (/@northwind/settings/people), as Laravel would. `@kit/ui` adds the pieces People and the modals import (src/kit-ui-people.ts).
const dropped = [/^@inertiajs\/vue3$/, /^@inertiaui\/modal-vue$/, /^@kit\/ui$/].map(String);
const aliases = [
    { find: /^@\/components\/ui\/input$/, replacement: here('src/shadcn/input/index.ts') },
    { find: /^@kit\/ui$/, replacement: here('src/kit-ui-people.ts') },
    ...base.resolve.alias.filter((alias) => !dropped.includes(String(alias.find))),
];
export default mergeConfig({ ...base, resolve: { ...base.resolve, alias: aliases } }, defineConfig({ base: '/', build: { outDir: here('dist-people'), rollupOptions: { input: { people: here('people.html') } } } }));

import { defineConfig, mergeConfig } from 'vite';
import path from 'node:path';
import base from './vite.config.js';

const here = (p) => path.resolve(import.meta.dirname, p);

// The auth pages' build (auth.html, into dist-auth): the base config with the real @inertiajs/vue3 in place of the stand-in, so the kit's guest pages run on
// Inertia's own <Form>, router and error modal, and shadcn-vue's real Input (src/shadcn/input), so a form reset meets the control a host installs.
const aliases = [{ find: /^@\/components\/ui\/input$/, replacement: here('src/shadcn/input/index.ts') }, ...base.resolve.alias.filter((alias) => String(alias.find) !== String(/^@inertiajs\/vue3$/))];
export default mergeConfig({ ...base, resolve: { ...base.resolve, alias: aliases } }, defineConfig({ build: { outDir: here('dist-auth'), rollupOptions: { input: here('auth.html') } } }));

import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwind from '@tailwindcss/vite';
import path from 'node:path';

const here = (p) => path.resolve(import.meta.dirname, p);

// The kit's stubs (core components, shell partials, session-ui pages) are copied (".stub" dropped) into .kit by run.mjs. Inertia and the shadcn pieces the tested components import are stand-ins, except the Checkbox and
// `@/components/ui/button`, the host's real shadcn-vue Button (src/button), so a variant's real classes (primary is bg-primary) paint in the browser; the menus are reka's own.
export default defineConfig({
    plugins: [vue({ script: { globalTypeFiles: [here('../types/kit.d.ts')] } }), tailwind()],
    base: './',
    logLevel: 'warn',
    resolve: {
        alias: [
            { find: /^@inertiajs\/vue3$/, replacement: here('src/inertia.js') },
            { find: /^@inertiaui\/modal-vue$/, replacement: here('src/modal.js') },
            { find: /^@\/components\/ui\/button$/, replacement: here('src/button/index.ts') },
            // shadcn-vue's real Checkbox (src/shadcn), so the checkbox checks drive the control a host installs; the other shadcn pieces are stand-ins.
            { find: /^@\/components\/ui\/checkbox$/, replacement: here('src/shadcn/checkbox/index.ts') },
            { find: /^cn$/, replacement: here('src/shadcn/cn.ts') },
            { find: /^@\/components\/ui\/.*$/, replacement: here('src/ui.js') },
            { find: /^@\/components\/Kit/, replacement: here('.kit/Kit') },
            { find: /^@kit\/ui$/, replacement: here('src/kit-ui.ts') },
            { find: /^@\/shell/, replacement: here('.kit/Shell') },
            // The shell's own Layout (?shell-width), whose partials import each other as `@kit/shell`.
            { find: /^@kit\/shell$/, replacement: here('.kit/Workspace/Partials/index.ts') },
            { find: /^@\/workspace/, replacement: here('.kit/Workspace') },
            { find: /^@\/pages/, replacement: here('.kit/Pages') },
        ],
    },
});

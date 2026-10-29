// Vue's SFC compiler resolves defineProps<Tey.Kit.…>() from the shipped types/kit.d.ts, as a host's @vitejs/plugin-vue does with it in script.globalTypeFiles.
// A namespace nested without `export` cannot be resolved there (TypeScript itself accepts it), so this compiles a page typed by a nested kit type and checks
// its runtime props. Run by bin/typecheck-components.sh after vue-tsc.
import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { compileScript, parse } from '@vue/compiler-sfc';

const types = fileURLToPath(new URL('../types/kit.d.ts', import.meta.url));
const fs = { fileExists: file => existsSync(file), readFile: file => (existsSync(file) ? readFileSync(file, 'utf8') : undefined) };
const pages = {
    'Tey.Kit.Features.Shell.Data.ShellData': ['kind', 'product'],
    'Tey.Kit.Features.SessionUi.ViewModels.WorkspaceDashboardViewModel': ['title', 'widgets'],
};
let failed = false;
for (const [type, expected] of Object.entries(pages)) {
    const { descriptor } = parse(`<script setup lang="ts">\nconst props = defineProps<${type}>();\n</script>\n<template><div /></template>\n`, { filename: '/kit/Page.vue' });
    try {
        const compiled = compileScript(descriptor, { id: 'kit-types', globalTypeFiles: [types], fs });
        const missing = expected.filter(prop => !new RegExp(`\\b${prop}: \\{`).test(compiled.content));
        if (missing.length) throw new Error(`props ${missing.join(', ')} not in the compiled runtime props`);
        console.log(`PASS defineProps<${type}>() resolves from types/kit.d.ts`);
    } catch (error) {
        failed = true;
        console.error(`FAIL defineProps<${type}>(): ${error.message.split('\n')[0]}`);
    }
}
process.exit(failed ? 1 : 0);

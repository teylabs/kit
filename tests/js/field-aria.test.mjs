import assert from 'node:assert/strict';
import test from 'node:test';
import { existsSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Field's aria contract: a control is described only by nodes that exist and is aria-invalid while the field has an error. useFieldAria runs under Vue's
// server renderer with real provide/inject (Vue from typecheck/, installed by the vue-tsc job, which also runs this file); each control's binding is checked by source.
const form = new URL('../../resources/features/core/components/Kit/Form/', import.meta.url);
const read = file => readFileSync(new URL(file, form), 'utf8');
const vueDir = new URL('../../typecheck/node_modules/vue/', import.meta.url);
const hasVue = existsSync(vueDir);

test('useFieldAria: no error, no description: no attributes; with them, only the rendered ids and aria-invalid', { skip: !hasVue && 'Vue is installed in typecheck/ by the vue-tsc job' }, async () => {
    const require = createRequire(new URL('../../typecheck/package.json', import.meta.url));
    const vue = require('vue');
    const { renderToString } = require('vue/server-renderer');
    const dir = mkdtempSync(join(tmpdir(), 'kit-field-aria-'));
    // The stub imports 'vue'; point it at typecheck's copy.
    writeFileSync(join(dir, 'fieldAria.ts'), read('fieldAria.ts.stub').replace("from 'vue'", `from '${pathToFileURL(require.resolve('vue')).href.replace(/\.js$/, '.js')}'`));
    const { FIELD_ARIA, useFieldAria } = await import(pathToFileURL(join(dir, 'fieldAria.ts')).href);
    const render = async (field, attrs = {}, invalid) => {
        const Control = { setup: () => { const aria = useFieldAria(attrs, invalid); return () => vue.h('input', { ...aria.value }); } };
        const Field = { setup: (_, { slots }) => { if (field) vue.provide(FIELD_ARIA, { id: 'email', describedBy: vue.computed(() => field.ids), invalid: vue.computed(() => field.invalid) }); return () => slots.default(); } };
        return (await renderToString(vue.createSSRApp({ render: () => vue.h(Field, null, { default: () => vue.h(Control) }) }))).replace(/<!--.*?-->/g, '');
    };
    assert.equal(await render({ ids: [], invalid: false }), '<input>', 'a field with no description or error: no aria-describedby, no aria-invalid');
    assert.equal(await render({ ids: [], invalid: false }, { 'aria-describedby': 'email-error email-description' }), '<input>', 'the old static ids are dropped while not rendered');
    assert.equal(await render({ ids: ['email-description'], invalid: false }), '<input aria-describedby="email-description">');
    assert.equal(await render({ ids: ['email-description', 'email-error'], invalid: true }, { 'aria-describedby': 'email-error hint' }), '<input aria-describedby="hint email-description email-error" aria-invalid="true">', 'other ids the page names are kept; the error id once');
    assert.equal(await render(null, { 'aria-describedby': 'hint' }, () => true), '<input aria-describedby="hint" aria-invalid="true">', 'outside a Field: the page\'s ids and the control\'s own invalid');
});

test('Field provides only the ids it renders; every Kit/Form control binds both attributes from it', () => {
    const field = read('Field.vue.stub');
    assert.match(field, /provide\(FIELD_ARIA, \{ id: props\.id, describedBy: computed\(\(\) => \[props\.description \? `\$\{props\.id\}-description` : '', message\.value \? `\$\{props\.id\}-error` : ''\]\.filter\(Boolean\)\), invalid: computed\(\(\) => Boolean\(message\.value\)\) \}\);/);
    for (const control of ['Input', 'Textarea', 'InputSelect', 'InputCheckbox', 'Select', 'FileInput', 'RadioCardGroup']) {
        const source = read(`${control}.vue.stub`);
        assert.match(source, /useFieldAria\(/, control);
        assert.match(source, /:aria-describedby="aria\['aria-describedby'\]"/, control);
        assert.match(source, /:aria-invalid="aria\['aria-invalid'\]"/, control);
    }
    assert.match(read('InputRichText.vue.stub'), /\.\.\.\(describedBy \? \{ 'aria-describedby': describedBy \} : \{\}\)/);
    assert.match(read('InputNumber.vue.stub'), /:aria-invalid="Boolean\(error\) \|\| undefined" :aria-describedby="error \? `\$\{id\}-error` : undefined"/, 'InputNumber renders its own Field and was already conditional');
});

test('kit pages no longer hard-code a field\'s description or error ids', () => {
    const pages = new URL('../../resources/features/', import.meta.url);
    const files = ['session-ui/pages/Auth/Login/Page.vue.stub', 'workspace-ui/pages/Workspace/Account/Security/Page.vue.stub', 'workspace-ui/pages/Workspace/Account/Profile/Page.vue.stub'];
    for (const file of files) assert.doesNotMatch(readFileSync(new URL(file, pages), 'utf8'), / aria-describedby="[a-z0-9_-]+-(?:error|description)/, file);
});

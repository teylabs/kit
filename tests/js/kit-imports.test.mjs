import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync, writeFileSync, mkdtempSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

// kitImports (docs/kit-imports.md) loads `@kit/ui` names straight from their files in dev, so a kit component that imports the barrel would pull the whole
// set back in. Kit components import each other by relative path, never through `@kit/ui` or the barrel file.
const kitDir = fileURLToPath(new URL('../../resources/js/components/Kit', import.meta.url));
const sources = readdirSync(kitDir, { recursive: true }).filter((file) => /\.(vue|ts|js)$/.test(file) && file !== 'index.ts' && !file.startsWith('kitImports.'));
const plugin = mkdtempSync(join(tmpdir(), 'kit-imports-'));
writeFileSync(join(plugin, 'kitImports.mjs'), readFileSync(join(kitDir, 'kitImports.js')));
const { readBarrel, kitImports } = await import(join(plugin, 'kitImports.mjs'));

test('no kit component imports the barrel', () => {
    assert.ok(sources.length > 100);
    const offenders = sources.filter((file) => {
        const code = readFileSync(join(kitDir, file), 'utf8');

        return /from\s*['"](@kit\/ui|(\.\.?\/)*(\.\.?|index(\.ts)?)|(\.\.?\/)+)['"]|import\(\s*['"]@kit\/ui['"]\s*\)/.test(code);
    });
    assert.deepEqual(offenders, []);
});

test('the barrel maps every value name to its file and skips type-only exports', () => {
    const { names } = readBarrel(readFileSync(join(kitDir, 'index.ts'), 'utf8'), '/app/Kit/index.ts');
    assert.deepEqual(names.get('Button'), { source: '/app/Kit/Form/Button.vue', imported: 'default' });
    assert.deepEqual(names.get('toggleSelection'), { source: '/app/Kit/Table/selection', imported: 'toggle' });
    assert.deepEqual(names.get('Checkbox'), { source: '/app/Kit/Form/InputCheckbox.vue', imported: 'default' });
    assert.deepEqual(names.get('DropdownMenuSub'), { source: '@/components/ui/dropdown-menu', imported: 'DropdownMenuSub' });
    for (const typeName of ['VisitData', 'AlertTier', 'MediaItem', 'RowLabel', 'PopoverWidth']) {
        assert.equal(names.has(typeName), false, typeName);
    }
    assert.ok(names.size > 250);
});

test('the plugin runs only under vite serve, after the other transforms', () => {
    const plugin = kitImports();
    assert.equal(plugin.apply, 'serve');
    assert.equal(plugin.enforce, 'post');
});

test("an app's own barrel: its names win, `export *` reaches the kit's files, and a name it can't place stays in the barrel", async () => {
    const app = mkdtempSync(join(tmpdir(), 'kit-imports-app-'));
    writeFileSync(join(app, 'ui.ts'), [
        "export { default as Button } from './MyButton.vue';",
        "export const Badge = 'mine';",
        "export * from '@/components/Kit';",
        "export type * from './types';",
    ].join('\n'));
    writeFileSync(join(app, 'kit.ts'), readFileSync(join(kitDir, 'index.ts')));
    const code = "import { Button, Badge, Page as KitPage, Unknown } from '@kit/ui'; export { Alert } from '@kit/ui';";
    const at = (text) => ({ start: code.indexOf(text), end: code.indexOf(text) + text.length });
    const specifier = (imported, local) => ({ type: 'ImportSpecifier', imported: { name: imported }, local: { name: local }, ...at(imported === local ? imported : `${imported} as ${local}`) });
    const ast = { body: [
        { type: 'ImportDeclaration', source: { value: '@kit/ui' }, specifiers: [specifier('Button', 'Button'), specifier('Badge', 'Badge'), specifier('Page', 'KitPage'), specifier('Unknown', 'Unknown')], ...at("import { Button, Badge, Page as KitPage, Unknown } from '@kit/ui';") },
        { type: 'ExportNamedDeclaration', source: { value: '@kit/ui' }, specifiers: [{ type: 'ExportSpecifier', local: { name: 'Alert' }, exported: { name: 'Alert' }, ...at('Alert') }], ...at("export { Alert } from '@kit/ui';") },
    ] };
    const context = {
        resolve: async (id) => ({ '@kit/ui': { id: join(app, 'ui.ts') }, '@/components/Kit': { id: join(app, 'kit.ts') } })[id] ?? null,
        parse: () => ast,
    };
    const { code: out } = await kitImports().transform.call(context, code, join(app, 'Page.vue'));
    assert.equal(out, [
        `import Button from ${JSON.stringify(join(app, 'MyButton.vue'))};`,
        `import KitPage from ${JSON.stringify(join(app, 'Layout/Page.vue'))};`,
        "import { Badge, Unknown } from \"@kit/ui\";",
        `export { default as Alert } from ${JSON.stringify(join(app, 'Feedback/Alert.vue'))};`,
    ].join(' '));
});

test('a name two `export *` branches bring in from different files is ambiguous and stays on the barrel, as in ESM', async () => {
    const app = mkdtempSync(join(tmpdir(), 'kit-imports-star-'));
    writeFileSync(join(app, 'ui.ts'), "export * from './a';\nexport * from './b';\n");
    writeFileSync(join(app, 'a.ts'), "export { default as Button } from './A.vue';\nexport { default as Page } from './Page.vue';\n");
    writeFileSync(join(app, 'b.ts'), "export { default as Button } from './B.vue';\n");
    const code = "import { Button, Page } from '@kit/ui';";
    const ast = { body: [{ type: 'ImportDeclaration', source: { value: '@kit/ui' }, start: 0, end: code.length, specifiers: [
        { type: 'ImportSpecifier', imported: { name: 'Button' }, local: { name: 'Button' }, start: 9, end: 15 },
        { type: 'ImportSpecifier', imported: { name: 'Page' }, local: { name: 'Page' }, start: 17, end: 21 },
    ] }] };
    const context = { resolve: async (id, from) => (id === '@kit/ui' ? { id: join(app, 'ui.ts') } : { id: join(app, `${id.slice(2)}.ts`) }), parse: () => ast };
    const { code: out } = await kitImports().transform.call(context, code, join(app, 'Page.vue'));
    assert.equal(out, `import Page from ${JSON.stringify(join(app, 'Page.vue'))}; import { Button } from "@kit/ui";`);
});

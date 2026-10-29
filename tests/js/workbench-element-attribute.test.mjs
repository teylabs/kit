import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The Workbench stage's element attribute: the host names the attribute its stage elements carry their id in (default data-workbench-element), and the stage
// toolbar's selection rects use it. selectedRects runs here over a fake viewport, with a stand-in for vue (it only needs provide/inject to import); the wiring
// through Workbench, useWorkbench and FloatingToolbar is checked by source.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-wb-attr-'));
const vue = join(dir, 'node_modules', 'vue');
mkdirSync(vue, { recursive: true });
writeFileSync(join(vue, 'package.json'), JSON.stringify({ name: 'vue', type: 'module', main: 'index.js' }));
writeFileSync(join(vue, 'index.js'), 'export const provide = () => {}; export const inject = (_, fallback) => fallback;\n');
writeFileSync(join(dir, 'workbenchStage.ts'), read('Workbench/workbenchStage.ts.stub'));
const { DEFAULT_ELEMENT_ATTRIBUTE, elementAttributeName, selectedRects } = await import(pathToFileURL(join(dir, 'workbenchStage.ts')).href);

/** A viewport whose elements carry attributes; it records the selector it was queried with. */
function viewport(elements) {
    const queried = [];
    return {
        queried,
        querySelectorAll(selector) {
            queried.push(selector);
            const name = selector.slice(1, -1);
            return elements.filter(el => name in el.attrs).map(el => ({ getAttribute: key => el.attrs[key] ?? null, getBoundingClientRect: () => el.box }));
        },
    };
}
const box = (left, top) => ({ top, left, width: 10, height: 10 });

test('the default attribute is data-workbench-element', () => {
    assert.equal(DEFAULT_ELEMENT_ATTRIBUTE, 'data-workbench-element');
    const stage = viewport([{ attrs: { 'data-workbench-element': 'a' }, box: box(1, 2) }, { attrs: { 'data-block-id': 'a' }, box: box(9, 9) }]);
    assert.deepEqual(selectedRects(stage, ['a']), [box(1, 2)]);
    assert.deepEqual(stage.queried, ['[data-workbench-element]']);
});

test('a named attribute finds the host\'s own elements', () => {
    const stage = viewport([{ attrs: { 'data-block-id': 'b1' }, box: box(5, 5) }, { attrs: { 'data-block-id': 'b2' }, box: box(6, 6) }, { attrs: { 'data-workbench-element': 'b1' }, box: box(0, 0) }]);
    assert.deepEqual(selectedRects(stage, ['b1'], 'data-block-id'), [box(5, 5)]);
    assert.deepEqual(stage.queried, ['[data-block-id]']);
});

test('a name or an attribute selector is accepted; anything else falls back to the default', () => {
    assert.equal(elementAttributeName('data-block-id'), 'data-block-id');
    assert.equal(elementAttributeName('[data-block-id]'), 'data-block-id');
    assert.equal(elementAttributeName('  data-x  '), 'data-x');
    assert.equal(elementAttributeName(null), 'data-workbench-element');
    assert.equal(elementAttributeName(''), 'data-workbench-element');
    assert.equal(elementAttributeName('div > [data-x]'), 'data-workbench-element', 'a selector cannot be injected');
    assert.equal(elementAttributeName('data-x"]'), 'data-workbench-element');
});

test('Workbench, useWorkbench and FloatingToolbar carry the attribute through', () => {
    const workbench = read('Workbench/Workbench.vue.stub');
    assert.match(workbench, /elementAttribute\?: string \| null;/);
    assert.match(workbench, /watch\(\(\) => props\.elementAttribute, name => \{ if \(name\) wb\.setElementAttribute\(name\); \}, \{ immediate: true \}\);/);
    const store = read('Workbench/useWorkbench.ts.stub');
    assert.match(store, /export function useWorkbench\(key: string, options: \{ elementSelector\?: string \| null \} = \{\}\): WorkbenchStore/);
    assert.match(store, /if \(options\.elementSelector\) entry\.store\.setElementAttribute\(options\.elementSelector\);/);
    assert.match(store, /const elementAttribute = ref\(DEFAULT_ELEMENT_ATTRIBUTE\);/);
    assert.match(store, /setElementAttribute\(name: string \| null \| undefined\) \{ elementAttribute\.value = elementAttributeName\(name\); \}/);
    assert.match(read('Layout/FloatingToolbar.vue.stub'), /selectedRects\(viewport, wb\.elements\.map\(element => element\.id\), wb\.elementAttribute\)/, 'stage mode and reposition() measure by the store\'s attribute');
    assert.match(read('index.ts.stub'), /elementAttributeName, DEFAULT_ELEMENT_ATTRIBUTE/);
});

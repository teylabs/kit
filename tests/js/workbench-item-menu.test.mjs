import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The navigator's per-item menu: the item's own action descriptors in a row menu whose button sits outside the listbox, opened by the button, by Shift+F10 or
// the context-menu key, or by a right-click on the item. The logic runs here; the components are checked by source with comments stripped, so prose can't satisfy
// an assertion. browser/run.mjs drives the same menu in Chromium with axe.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1').replace(/<!--[\s\S]*?-->/g, '');
const source = path => strip(readFileSync(new URL(path, kit), 'utf8'));
const dir = mkdtempSync(join(tmpdir(), 'kit-workbench-menu-'));
writeFileSync(join(dir, 'workbench.ts'), readFileSync(new URL('Workbench/workbench.ts.stub', kit), 'utf8'));
const wb = await import(pathToFileURL(join(dir, 'workbench.ts')).href);

test('Shift+F10 and the context-menu key open an item menu; nothing else does', () => {
    assert.equal(wb.opensItemMenu({ key: 'F10', shiftKey: true }), true);
    assert.equal(wb.opensItemMenu({ key: 'ContextMenu' }), true);
    assert.equal(wb.opensItemMenu({ key: 'F10' }), false);
    assert.equal(wb.opensItemMenu({ key: 'F10', shiftKey: true, ctrlKey: true }), false);
    assert.equal(wb.opensItemMenu({ key: 'ContextMenu', altKey: true }), false);
    assert.equal(wb.opensItemMenu({ key: 'ArrowDown' }), false);
    assert.equal(wb.workbenchCommand({ key: 'F10', shiftKey: true }, { inNavigator: true, typing: false }), null, 'no Workbench command claims Shift+F10');
});

test('an item\'s menu shows its own descriptors, none without them, and the button is named for the item', () => {
    const actions = [{ key: 'duplicate', label: 'Duplicate', url: '/d', method: 'post' }];
    assert.deepEqual(wb.itemActions({ actions }), actions);
    assert.deepEqual(wb.itemActions({}), []);
    assert.deepEqual(wb.itemActions({ actions: [] }), []);
    assert.equal(wb.itemMenuLabel({ label: 'Slide 3' }), 'Actions for Slide 3');
    assert.equal(wb.itemMenuLabel({ label: 'Slide 3' }, 'Options for'), 'Options for Slide 3');
});


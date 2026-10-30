import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-search-'));
copyFileSync(new URL('Search/search.ts.stub', kit), join(dir, 'search.ts'));
const { flatten, move, opensSearch } = await import(pathToFileURL(join(dir, 'search.ts')).href);
const key = (overrides = {}) => ({ key: 'k', metaKey: false, ctrlKey: false, altKey: false, isComposing: false, defaultPrevented: false, ...overrides });

test('results flatten in group order with ids unique across groups', () => {
    const options = flatten([{ key: 'app.notes', label: 'Notes', results: [{ id: '1', label: 'A' }] }, { key: 'kit.people', label: 'People', results: [{ id: '1', label: 'B' }] }]);
    assert.deepEqual(options.map((option) => option.label), ['A', 'B']);
    assert.notEqual(options[0].optionId, options[1].optionId);
    assert.match(options[0].optionId, /^[A-Za-z0-9_-]+$/);
});

test('arrow keys wrap, and nothing is active without results', () => {
    assert.equal(move(-1, 3, 1), 0);
    assert.equal(move(-1, 3, -1), 2);
    assert.equal(move(2, 3, 1), 0);
    assert.equal(move(0, 3, -1), 2);
    assert.equal(move(0, 0, 1), -1);
});

test('⌘K or Ctrl+K opens search anywhere, `/` only when not typing, never over a dialog or while composing', () => {
    assert.equal(opensSearch(key({ metaKey: true }), true, false), true);
    assert.equal(opensSearch(key({ key: 'K', ctrlKey: true }), false, false), true);
    assert.equal(opensSearch(key({ key: '/' }), false, false), true);
    assert.equal(opensSearch(key({ key: '/' }), true, false), false);
    assert.equal(opensSearch(key({ metaKey: true }), false, true), false);
    assert.equal(opensSearch(key({ metaKey: true, isComposing: true }), false, false), false);
    assert.equal(opensSearch(key({ metaKey: true, altKey: true }), false, false), false);
    assert.equal(opensSearch(key({ metaKey: true, defaultPrevented: true }), false, false), false);
});

test('the bars register with useStickyBars, and the header shows search only when the server sends it', () => {
    assert.match(readFileSync(new URL('Layout/StickyBar.vue.stub', kit), 'utf8'), /const pinned = computed\(\(\) => props\.pinned \|\| atFoot\.value \|\| \(props\.sticky && scrolled\.value\)\);\nuseStickyBars\(\)\.track\(bar, \(\) => pinned\.value\)/);
    const layout = readFileSync(new URL('../../resources/features/shell/pages/Workspace/Layout.vue.stub', import.meta.url), 'utf8');
    assert.match(layout, /<div v-if="app\.search"[^>]*data-shell-search>/);
    assert.match(readFileSync(new URL('Search/SearchBox.vue.stub', kit), 'utf8'), /role="combobox"/);
});

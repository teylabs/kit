import assert from 'node:assert/strict';
import test from 'node:test';
import { execFileSync } from 'node:child_process';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

// The stub is TypeScript with a .stub suffix; node strips types from a plain .ts copy.
const dir = mkdtempSync(join(tmpdir(), 'kit-input-select-'));
copyFileSync(new URL('../../resources/features/core/components/Kit/Form/inputSelect.ts.stub', import.meta.url), join(dir, 'inputSelect.ts'));
const s = await import(pathToFileURL(join(dir, 'inputSelect.ts')).href);
const lang = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(require $argv[1]);', fileURLToPath(new URL('../../lang/en/ui.php', import.meta.url))]).toString());

const fruit = ['Apple', 'Banana', 'Cherry', 'Crème brûlée', 'Date', 'Elderberry', 'Fig', 'Grape', 'Honeydew'].map(label => ({ value: label.toLowerCase(), label, disabled: label === 'Banana' }));
const groups = [{ label: 'Fruit', options: fruit.slice(0, 3) }, { label: 'Veg', options: [{ value: 'kale', label: 'Kale' }] }];

test('search turns on past 8 options, groups counted through; forced either way; remote means on', () => {
    assert.equal(s.searchEnabled(null, fruit.slice(0, 8), 'local'), false);
    assert.equal(s.searchEnabled(null, fruit, 'local'), true);
    assert.equal(s.searchEnabled(null, [{ label: 'A', options: fruit.slice(0, 5) }, { label: 'B', options: fruit.slice(5) }], 'local'), true);
    assert.equal(s.searchEnabled(true, fruit.slice(0, 2), 'local'), true);
    assert.equal(s.searchEnabled(false, fruit, 'local'), false);
    assert.equal(s.searchEnabled(null, [], 'remote'), true);
});
test('filtering is case- and accent-insensitive, hides empty groups, and leaves remote lists alone', () => {
    assert.deepEqual(s.filterGroups(fruit, 'CREME', 'local')[0].options.map(o => o.label), ['Crème brûlée']);
    assert.deepEqual(s.filterGroups(groups, 'kal', 'local').map(g => g.label), ['Veg']);
    assert.deepEqual(s.filterGroups(groups, 'zzz', 'local'), []);
    assert.equal(s.filterGroups(fruit, 'zzz', 'remote')[0].options.length, fruit.length);
});
test('chosen first inside each group, from the values chosen at open', () => {
    assert.deepEqual(s.chosenFirst(s.groupsOf(groups), ['cherry', 'kale'])[0].options.map(o => o.value), ['cherry', 'apple', 'banana']);
});
test('multiple keeps choice order; select all adds and removes the shown enabled options', () => {
    assert.deepEqual(s.toggleValue(['fig', 'apple'], 'date'), ['fig', 'apple', 'date']);
    assert.deepEqual(s.toggleValue(['fig', 'apple'], 'fig'), ['apple']);
    const shown = fruit.slice(0, 3);
    assert.deepEqual(s.selectAll(['fig'], shown), { all: false, values: ['fig', 'apple', 'cherry'] });
    assert.deepEqual(s.selectAll(['fig', 'apple', 'cherry'], shown), { all: true, values: ['fig'] });
    assert.deepEqual(s.valuesOf(null), []);
    assert.deepEqual(s.valuesOf(0), [0]);
});
test('the create row shows only for text with no exact label match', () => {
    assert.equal(s.showCreate('  apple ', fruit), false);
    assert.equal(s.showCreate('appl', fruit), true);
    assert.equal(s.showCreate('   ', fruit), false);
});
test('remote search waits for the minimum length; 0 emits for any text', () => {
    assert.equal(s.searchReady(' m ', 2), false);
    assert.equal(s.searchReady('ma', 2), true);
    assert.equal(s.searchReady('', 0), true);
});
test('highlight moves skip disabled rows, stop at the ends, jump ten and to either end', () => {
    const rows = ['a', null, 'c', 'd', null, 'f'];
    assert.equal(s.moveHighlight(rows, 0, 1), 2);
    assert.equal(s.moveHighlight(rows, 5, 1), 5);
    assert.equal(s.moveHighlight(rows, 0, -1), 0);
    assert.equal(s.moveHighlight(rows, 5, -10), 0);
    assert.equal(s.moveHighlight(rows, 2, 'last'), 5);
    assert.equal(s.moveHighlight(rows, -1, 1), 0);
    assert.equal(s.moveHighlight([null], -1, 1), -1);
});
test('chips by keyboard: Backspace twice, ArrowLeft and Delete, ArrowRight back into the text', () => {
    assert.deepEqual(s.chipKey('Backspace', null, 3), { chip: 2, remove: null, handled: true });
    assert.deepEqual(s.chipKey('Backspace', 2, 3), { chip: null, remove: 2, handled: true });
    assert.deepEqual(s.chipKey('ArrowLeft', 2, 3), { chip: 1, remove: null, handled: true });
    assert.deepEqual(s.chipKey('Delete', 1, 3), { chip: null, remove: 1, handled: true });
    assert.deepEqual(s.chipKey('ArrowRight', 2, 3), { chip: null, remove: null, handled: true });
    assert.equal(s.chipKey('ArrowRight', null, 3).handled, false);
    assert.equal(s.chipKey('Backspace', null, 0).handled, false);
});
test('every default string comes from the lang file, messages override one, a gap shows its key', () => {
    const keys = [...s.inputSelectMessageKeys].sort();
    const flat = (node, prefix = '') => Object.entries(node).flatMap(([k, v]) => (typeof v === 'string' ? [prefix + k] : flat(v, `${prefix}${k}.`)));
    assert.deepEqual(flat(lang.select).sort(), keys);
    const m = s.selectMessages(lang.select, { empty: 'Nothing here' });
    assert.equal(m.empty, 'Nothing here');
    assert.equal(m.loading, 'Loading…');
    assert.equal(s.interpolate(m.remove, { label: 'Fig' }), 'Remove Fig');
    assert.equal(s.interpolate(m.empty_query, { query: 'zz' }), 'No results for “zz”');
    assert.equal(s.resultsMessage(m, 1), '1 result');
    assert.equal(s.resultsMessage(m, 4), '4 results');
    assert.equal(s.resultsMessage(m, 0), 'No results');
    assert.equal(s.selectMessages(undefined).clear, 'select.clear');
});

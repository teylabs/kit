import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const table = new URL('../../resources/features/core/components/Kit/Table/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-selection-'));
copyFileSync(new URL('selection.ts.stub', table), join(dir, 'selection.ts'));
const { emptySelection, isSelected, pageState, toggle, togglePage } = await import(pathToFileURL(join(dir, 'selection.ts')).href);
const read = (name) => readFileSync(new URL(name, table), 'utf8');

test('a row toggles in and out, matching ids by value whatever their type', () => {
    const one = toggle(emptySelection(), 3);
    assert.equal(isSelected(one, '3'), true);
    assert.deepEqual(toggle(one, '3').ids, []);
});

test('the page checkbox reads all, some or none of the visible rows', () => {
    assert.equal(pageState(emptySelection(), [1, 2]), 'none');
    assert.equal(pageState({ ids: [1] }, [1, 2]), 'some');
    assert.equal(pageState({ ids: [1, 2, 9] }, [1, 2]), 'all');
    assert.equal(pageState(emptySelection(), []), 'none', 'an empty page is never all selected');
});

test('toggling the page selects or clears only the visible rows, keeping other pages', () => {
    assert.deepEqual(togglePage({ ids: [9, 1] }, [1, 2]).ids, [9, 1, 2]);
    assert.deepEqual(togglePage({ ids: [9, 1, 2] }, [1, 2]).ids, [9]);
});

test('the table is selectable only with bulk actions, and the bulk bar registers as a sticky bar', () => {
    assert.match(read('DataTable.vue.stub'), /const selectable = computed\(\(\) => \(props\.table\.bulkActions\?\.length \?\? 0\) > 0\)/);
    const bar = read('BulkBar.vue.stub');
    assert.match(bar, /<FloatingToolbar v-if="ids\.length" mode="float"/);
    assert.match(bar, /data: \{ ids: props\.ids \}/);
    assert.match(bar, /onSuccess: \(\) => emit\('clear'\)/);
});

test('the toolbar offers only the server\'s filter choices', () => {
    assert.match(read('IndexToolbar.vue.stub'), /v-for="choice in choices"[\s\S]*:options="choice\.options"/);
});

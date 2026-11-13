import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// DataTable's row label, get descriptors as links (RowActions, ActionBar's segments, the ⌄ menu rows) and Badge. The pure parts run here; the components are checked by
// source (no browser runner).
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-table-2604-'));
writeFileSync(join(dir, 'rowLabel.ts'), read('Table/rowLabel.ts'));
writeFileSync(join(dir, 'actionPlacement.ts'), read('Action/actionPlacement.ts'));
const { rowName } = await import(pathToFileURL(join(dir, 'rowLabel.ts')).href);
const { rendersAsLink } = await import(pathToFileURL(join(dir, 'actionPlacement.ts')).href);

test('rowLabel names the row by a column key or a function, falling back to the row number', () => {
    const row = { id: 7, name: 'Ana Lopez', email: '' };
    assert.equal(rowName(row, 2, 'name'), 'Ana Lopez');
    assert.equal(rowName(row, 2, r => `${r.name} (#${r.id})`), 'Ana Lopez (#7)');
    assert.equal(rowName(row, 2, 'email'), 'row 3', 'an empty value falls back');
    assert.equal(rowName(row, 2, 'missing'), 'row 3');
    assert.equal(rowName(row, 0, null), 'row 1');
});

test('a get action without a confirmation renders as a link; post, confirm, dialog and disabled ones stay buttons', () => {
    assert.equal(rendersAsLink({ method: 'get', key: 'view' }), true);
    assert.equal(rendersAsLink({ method: 'GET', key: 'view' }), true);
    assert.equal(rendersAsLink({ method: 'post', key: 'archive' }), false);
    assert.equal(rendersAsLink({ method: 'get', key: 'x', confirm: { title: 't', description: 'd', label: 'l' } }), false);
    assert.equal(rendersAsLink({ method: 'get', key: 'x', intent: 'dialog' }), false);
    assert.equal(rendersAsLink({ method: 'get', key: 'x', disabled: true }), false);
    assert.equal(rendersAsLink({ method: 'get', key: 'edit' }, ['edit']), false, 'an intercepted action is the page\'s');
});

test('a confirmed GET asks before it navigates (it is never a bare link that skips the confirmation)', () => {
    const confirmed = { key: 'export', label: 'Export', url: '/people/export', method: 'get', confirm: { title: 'Export everyone?', description: 'This can take a while.', label: 'Export' } };
    assert.equal(rendersAsLink(confirmed), false, 'a GET with a confirm is not rendered as a link');
});

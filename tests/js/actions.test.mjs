import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The stubs are TypeScript with a .stub suffix; node strips types from a plain .ts copy.
const dir = mkdtempSync(join(tmpdir(), 'kit-actions-'));
copyFileSync(new URL('../../resources/features/core/components/Kit/Action/actionPlacement.ts.stub', import.meta.url), join(dir, 'actionPlacement.ts'));
const { actionIconName, isNavigation, layoutActions, opensNewTab, placeActions } = await import(pathToFileURL(join(dir, 'actionPlacement.ts')).href);
const a = (label, extra = {}) => ({ label, url: `/${label}`, method: 'post', ...extra });
const labels = list => list.map(action => action.label);

test('declared layout: joins non-adjacent groups, menus last, menu-only groups, order kept', () => {
    const parts = layoutActions([a('Edit', { group: 'record', menu: true }), a('Send'), a('Copy', { group: 'tools' }), a('Archive', { group: 'record' }), a('Void', { group: 'only', menu: true }), a('Paste', { group: 'tools' })]);
    assert.deepEqual(parts.map(part => part.key), ['single-1', 'tools', 'record', 'only']);
    assert.deepEqual(labels(parts[1].inline), ['Copy', 'Paste']);
    assert.deepEqual([labels(parts[2].inline), labels(parts[2].menu)], [['Archive'], ['Edit']]);
    assert.deepEqual([parts[3].inline, labels(parts[3].menu)], [[], ['Void']]);
});
test('no group, or menu alone, means no declared layout', () => {
    assert.equal(layoutActions([a('One'), a('Two', { menu: true })]), null);
    assert.equal(layoutActions([a('One', { group: '' })]), null);
});
test('placement: navigation ignores the limit, operations overflow, destructive rules', () => {
    const nav = a('Open', { method: 'GET', disabled: true });
    assert.deepEqual(labels(placeActions([nav, a('A'), a('B')]).inline), ['Open', 'A', 'B']);
    const three = placeActions([nav, a('A'), a('B'), a('C')]);
    assert.deepEqual([labels(three.inline), labels(three.menu)], [['Open', 'A'], ['B', 'C']]);
    assert.deepEqual(labels(placeActions([a('A'), a('B')], -3).menu), ['A', 'B']);
    const del = a('Delete', { destructive: true });
    assert.deepEqual(labels(placeActions([a('A'), del]).menu), ['Delete']);
    assert.deepEqual(labels(placeActions([del, a('Purge', { destructive: true })]).inline), ['Delete', 'Purge']);
});
test('dialog GETs and intercepted keys are operations', () => {
    assert.equal(isNavigation(a('Pay', { method: 'get', intent: 'dialog' })), false);
    assert.equal(isNavigation(a('Pay', { method: 'get', key: 'pay' }), ['pay']), false);
    assert.equal(isNavigation(a('Pay', { method: 'get' })), true);
});
test('icons and new-tab labels', () => {
    assert.equal(actionIconName(a('Add note')), 'plus');
    assert.equal(actionIconName(a('Newsletter')), null);
    assert.equal(actionIconName(a('Rename', { icon: 'unknown' })), 'unknown');
    assert.equal(actionIconName(a('Rename', { key: 'edit' })), null);
    assert.equal(actionIconName(a('Rename', { key: 'edit' }), true), 'pencil');
    assert.equal(opensNewTab(a('PDF', { intent: 'document' })), true);
    assert.equal(opensNewTab(a('PDF', { intent: 'document', newTab: false })), false);
    assert.equal(opensNewTab(a('Site', { newTab: true })), true);
});

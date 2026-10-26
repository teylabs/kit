import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The stubs are TypeScript with a .stub suffix; node strips types from a plain .ts copy. The Vue context (useLocked.ts) wraps this module and is not tested here.
const dir = mkdtempSync(join(tmpdir(), 'kit-locked-'));
copyFileSync(new URL('../../resources/features/core/components/Kit/Form/locked.ts.stub', import.meta.url), join(dir, 'locked.ts'));
const { NOTICES, UNLOCKED, VIEW_ONLY, fileSize, implicitSubmit, readOnlyLabel, resolveLocked, withoutEditActions } = await import(pathToFileURL(join(dir, 'locked.ts')).href);
const save = { label: 'Save', url: '/settings', method: 'put' };

test('the server decides: a null save locks, a descriptor leaves the area editable', () => {
    assert.equal(resolveLocked({ save: null }).locked, true);
    assert.equal(resolveLocked({ save }).locked, false);
    assert.equal(resolveLocked({ save: { ...save, disabled: true, reason: 'Closed' } }).locked, false, 'a disabled descriptor is FormActions\'s case, not a lock');
    assert.deepEqual(UNLOCKED, { locked: false, notice: NOTICES.many, reason: null });
});
test('a locked ancestor cannot be unlocked by an editable descendant', () => {
    const outer = resolveLocked({ save: null });
    assert.equal(resolveLocked({ save }, outer).locked, true);
    assert.equal(resolveLocked({ save: null }, resolveLocked({ save })).locked, true);
    assert.equal(resolveLocked({ save }, resolveLocked({ save })).locked, false);
});
test('the notice: plural by default, singular with quantity one, replaced by notice, with reason beside it', () => {
    assert.equal(resolveLocked({ save: null }).notice, 'You can view these settings but not change them.');
    assert.equal(resolveLocked({ save: null, quantity: 'one' }).notice, 'You can view this setting but not change it.');
    assert.equal(resolveLocked({ save: null, quantity: 'one', notice: 'You can view this pricing rule but not change it.' }).notice, 'You can view this pricing rule but not change it.');
    assert.equal(resolveLocked({ save: null }).reason, null);
    assert.equal(resolveLocked({ save: null, reason: 'Reopen the record to edit.' }).reason, 'Reopen the record to edit.');
    assert.equal(resolveLocked({ save: null, reason: undefined }).reason, null);
});
test('the pill label is the reason, else "View only"', () => {
    assert.equal(readOnlyLabel(resolveLocked({ save: null, reason: 'Reopen the record to edit.' })), 'Reopen the record to edit.');
    assert.equal(readOnlyLabel(resolveLocked({ save: null })), VIEW_ONLY);
    assert.equal(VIEW_ONLY, 'View only');
});
test('edit descriptors are hidden only while locked; header actions stay', () => {
    const actions = [{ label: 'Reopen', url: '/reopen', method: 'post' }, { label: 'Add row', url: '/rows', method: 'post', intent: 'edit' }, { label: 'Download', url: '/pdf', method: 'get', intent: 'document' }, { label: 'Edit', url: '/edit', method: 'get', intent: 'dialog' }];
    assert.deepEqual(withoutEditActions(actions, true).map(action => action.label), ['Reopen', 'Download', 'Edit']);
    assert.equal(withoutEditActions(actions, false), actions);
});
test('Enter in a single-line input would submit implicitly; a textarea, button or checkbox would not', () => {
    assert.equal(implicitSubmit('Enter', 'INPUT'), true);
    assert.equal(implicitSubmit('Enter', 'INPUT', 'text'), true);
    assert.equal(implicitSubmit('Enter', 'input', 'email'), true);
    assert.equal(implicitSubmit('Enter', 'TEXTAREA'), false);
    assert.equal(implicitSubmit('Enter', 'BUTTON'), false);
    assert.equal(implicitSubmit('Enter', 'INPUT', 'checkbox'), false);
    assert.equal(implicitSubmit('Enter', 'INPUT', 'submit'), false);
    assert.equal(implicitSubmit(' ', 'INPUT'), false);
    assert.equal(implicitSubmit('Enter', 'A'), false);
});
test('a file size reads as bytes, KB or MB', () => {
    assert.equal(fileSize(512), '512 B');
    assert.equal(fileSize(20480), '20 KB');
    assert.equal(fileSize(2 * 1024 * 1024 + 512 * 1024), '2.5 MB');
});

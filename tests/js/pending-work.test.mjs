import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdirSync, mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The pending-work registry (Kit/Form/pendingWork.ts) the leave-page guard reads, over a stand-in for vue: ref is a box, computed a getter box, readonly the identity.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-pending-work-'));
const vue = join(dir, 'node_modules', 'vue');
mkdirSync(vue, { recursive: true });
writeFileSync(join(vue, 'package.json'), JSON.stringify({ name: 'vue', type: 'module', main: 'index.js' }));
writeFileSync(join(vue, 'index.js'), 'export const ref = (value) => ({ value });\nexport const computed = (get) => ({ get value() { return get(); } });\nexport const readonly = (value) => value;\n');
copyFileSync(new URL('Form/pendingWork.ts.stub', kit), join(dir, 'pendingWork.ts'));
const { createPendingWork, usePendingWork } = await import(pathToFileURL(join(dir, 'pendingWork.ts')).href);

test('begin() counts a task until its end() is called; end() twice ends it once', () => {
    const work = createPendingWork();
    assert.equal(work.pending.value, false);
    const one = work.begin();
    const two = work.begin();
    assert.equal(work.count.value, 2);
    assert.equal(work.pending.value, true);
    one();
    one();
    assert.equal(work.count.value, 1, 'a second end() does not end the other task');
    two();
    assert.equal(work.pending.value, false);
});

test('track() holds a promise until it resolves, and resolves with its value', async () => {
    const work = createPendingWork();
    let resolve;
    const tracked = work.track(new Promise((r) => { resolve = r; }));
    assert.equal(work.pending.value, true);
    resolve('done');
    assert.equal(await tracked, 'done');
    assert.equal(work.pending.value, false);
});

test('track() holds a promise until it rejects, and rejects as it does', async () => {
    const work = createPendingWork();
    let reject;
    const tracked = work.track(new Promise((_, r) => { reject = r; }));
    assert.equal(work.pending.value, true);
    reject(new Error('upload failed'));
    await assert.rejects(tracked, /upload failed/);
    assert.equal(work.pending.value, false);
});

test('usePendingWork() is one registry per page; createPendingWork() makes a separate one', () => {
    assert.equal(usePendingWork(), usePendingWork());
    const separate = createPendingWork();
    const end = separate.begin();
    assert.equal(usePendingWork().pending.value, false);
    end();
});

test('the leave guard reads the page registry, and BulkDrop\'s direct upload joins it until it ends', async () => {
    const { readFileSync } = await import('node:fs');
    const frame = readFileSync(new URL('Form/formFrame.ts.stub', kit), 'utf8');
    const drop = readFileSync(new URL('Form/BulkDrop.vue.stub', kit), 'utf8');
    assert.match(frame, /const work = usePendingWork\(\);\s*const unsaved = \(\) => dirty\(\) \|\| work\.pending\.value;/);
    assert.match(drop, /const end = work\.begin\(\);/);
    assert.match(drop, /\} finally \{\s*end\(\);/);
});

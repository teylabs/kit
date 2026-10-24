import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The stubs are TypeScript with a .stub suffix; node strips types from a plain .ts copy.
const dir = mkdtempSync(join(tmpdir(), 'kit-split-drop-'));
const load = async path => {
    const name = path.split('/').pop();
    copyFileSync(new URL(`../../resources/features/core/components/Kit/${path}.ts.stub`, import.meta.url), join(dir, `${name}.ts`));
    return import(pathToFileURL(join(dir, `${name}.ts`)).href);
};
const { splitStorage, splitFallbackKey } = await load('Layout/splitState');
const { checkSelection, describeProblem, sizeHint } = await load('Form/captureFiles');

const memory = (seed = {}) => { const items = { ...seed }; return { items, getItem: key => items[key] ?? null, setItem: (key, value) => { items[key] = String(value); } }; };
const flush = () => new Promise(resolve => setTimeout(resolve, 0));
const key = splitFallbackKey('person-1', 'builder');

test('server state wins over the local fallback, which is read only without it', () => {
    const local = memory({ [key]: 'local' });
    assert.equal(splitStorage({ key: 'builder', scope: 'person-1', state: 'server', local: () => local }).getItem('x'), 'server');
    assert.equal(splitStorage({ key: 'builder', scope: 'person-1', local: () => local }).getItem('x'), 'local');
    assert.equal(key, 'kit:split:person-1:builder');
});

test('a changed value goes to the preference as { key, value }; unchanged values send nothing', async () => {
    const sent = [];
    const saved = [];
    const local = memory();
    const storage = splitStorage({ key: 'builder', scope: 'person-1', state: 'a', preference: { url: '/prefs', method: 'post' }, send: async (preference, body) => { sent.push([preference.url, body]); return true; }, local: () => local, saved: value => saved.push(value) });
    storage.setItem('x', 'a');
    storage.setItem('x', 'b');
    await flush();
    assert.deepEqual(sent, [['/prefs', { key: 'builder', value: 'b' }]]);
    assert.deepEqual(saved, ['b']);
    assert.deepEqual(local.items, {});
    assert.equal(storage.getItem('x'), 'b');
});

test('no descriptor, a rejected write or a network failure fall back to localStorage', async () => {
    for (const send of [undefined, async () => false, async () => { throw new Error('offline'); }]) {
        const local = memory();
        splitStorage({ key: 'builder', scope: 'person-1', preference: send ? { url: '/prefs', method: 'post' } : null, send, local: () => local }).setItem('x', 'v');
        await flush();
        assert.deepEqual(local.items, { [key]: 'v' });
    }
});

test('blocked storage never throws', () => {
    const blocked = () => { throw new Error('SecurityError'); };
    const storage = splitStorage({ key: 'builder', scope: 'person-1', local: () => ({ getItem: blocked, setItem: blocked }) });
    assert.equal(storage.getItem('x'), null);
    assert.doesNotThrow(() => storage.setItem('x', 'v'));
});

test('a selection fits only by type, size and count (one file by default); otherwise the first problem is named', () => {
    const limits = { accept: ['application/pdf', 'image/jpeg'], maxBytes: 100_000_000 };
    const pdf = { name: 'a.pdf', type: 'application/pdf', size: 100 };
    assert.equal(checkSelection([pdf], limits), null);
    assert.equal(checkSelection([], limits), null);
    assert.equal(checkSelection([pdf, pdf], { ...limits, maxFiles: 2 }), null);
    assert.deepEqual(checkSelection([pdf, pdf], limits), { kind: 'count' });
    assert.deepEqual(checkSelection([{ ...pdf, name: 'plan.pdf', size: 100_000_001 }], limits), { kind: 'size', name: 'plan.pdf' });
    assert.deepEqual(checkSelection([pdf, { name: 'setup.exe', type: 'application/x-msdownload', size: 1 }], { ...limits, maxFiles: 5 }), { kind: 'type', name: 'setup.exe' });
    assert.deepEqual(checkSelection([{ name: 'big.exe', type: 'x/y', size: 999_999_999 }], limits), { kind: 'type', name: 'big.exe' });
});

test('each problem reads as one sentence naming the file and the reason', () => {
    const limits = { accept: ['application/pdf'], maxBytes: 100_000_000, maxFiles: 100 };
    assert.equal(describeProblem({ kind: 'type', name: 'setup.exe' }, limits), "setup.exe isn't an accepted type");
    assert.equal(describeProblem({ kind: 'size', name: 'plan.pdf' }, limits), 'plan.pdf is over 100 MB');
    assert.equal(describeProblem({ kind: 'size', name: 'a.pdf' }, { ...limits, maxBytes: 2_500_000 }), 'a.pdf is over 2.5 MB');
    assert.equal(describeProblem({ kind: 'count' }, limits), 'Only 100 files at once');
    assert.equal(describeProblem({ kind: 'count' }, { ...limits, maxFiles: undefined }), 'Only 1 file at once');
});

test('the fallback hint states the per-file limit', () => {
    assert.equal(sizeHint(100_000_000), 'Up to 100 MB each');
    assert.equal(sizeHint(2_500_000), 'Up to 2.5 MB each');
});

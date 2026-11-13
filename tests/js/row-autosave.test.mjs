import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { flat } from './shape.mjs';

// Row autosave (docs/row-autosave.md): rowSave.ts (plain TypeScript) runs here against a fake server; the Vue wrapper and the status are checked by source.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const flatRead = path => flat(read(path));
const dir = mkdtempSync(join(tmpdir(), 'kit-row-save-'));
writeFileSync(join(dir, 'rowSave.ts'), read('Form/rowSave.ts'));
const { createRowSaver, rowSaveFailure } = await import(pathToFileURL(join(dir, 'rowSave.ts')).href);
const tick = (ms = 0) => new Promise(resolve => setTimeout(resolve, ms));

function server() {
    const calls = [];
    const replies = [];
    return {
        calls,
        reply: result => replies.push(result),
        send: (id, patch, version) => new Promise(resolve => calls.push({ id, patch, version, resolve })),
        answer: (index, result) => calls[index].resolve(result),
    };
}

test('edits within the debounce go out as one save, with the row version', async () => {
    const s = server();
    const saver = createRowSaver({ send: s.send, debounce: 5, version: () => 3 });
    saver.change('a', { rate: 1 });
    saver.change('a', { rate: 2, label: 'Tier' });
    assert.equal(saver.state('a'), 'pending');
    await tick(15);
    assert.deepEqual(s.calls.map(c => [c.id, c.patch, c.version]), [['a', { rate: 2, label: 'Tier' }, 3]]);
    assert.equal(saver.state('a'), 'saving');
    s.answer(0, { ok: true, version: 4 });
    await tick();
    assert.equal(saver.state('a'), 'saved');
    assert.equal(saver.pending(), false);
});

test('one save per row at a time: edits made in flight follow with the version the reply returned', async () => {
    const s = server();
    const saver = createRowSaver({ send: s.send, debounce: 0, version: () => 1 });
    saver.change('a', { rate: 1 });
    await tick(5);
    saver.change('a', { rate: 2 });
    await saver.flush('a');
    assert.equal(s.calls.length, 1, 'the second waits for the first');
    s.answer(0, { ok: true, version: 2 });
    await tick(5);
    assert.deepEqual([s.calls.length, s.calls[1].patch, s.calls[1].version], [2, { rate: 2 }, 2]);
    s.answer(1, { ok: true, version: 3 });
    await tick();
    assert.equal(saver.state('a'), 'saved');
});

test('rows save independently', async () => {
    const s = server();
    const saver = createRowSaver({ send: s.send, debounce: 0 });
    saver.change('a', { x: 1 });
    saver.change('b', { x: 2 });
    await tick(5);
    assert.deepEqual(s.calls.map(c => c.id), ['a', 'b']);
});

test('a conflict marks the row stale and sends nothing more until reload takes the server copy', async () => {
    const s = server();
    const seen = [];
    const saver = createRowSaver({ send: s.send, debounce: 0, version: () => 1, onChange: (id, state, d) => seen.push([state, d.reason]) });
    saver.change('a', { rate: 1 });
    await tick(5);
    s.answer(0, { ok: false, conflict: true, reason: 'Changed by someone else.' });
    await tick();
    assert.equal(saver.state('a'), 'stale');
    saver.change('a', { rate: 9 });
    await saver.flush('a');
    await tick(5);
    assert.equal(s.calls.length, 1, 'a stale row never overwrites the newer copy');
    assert.equal(saver.pending(), true, 'its edits still count as unsaved');
    saver.reload('a', 7);
    assert.equal(saver.state('a'), 'idle');
    assert.equal(saver.pending(), false);
    saver.change('a', { rate: 2 });
    await tick(5);
    assert.deepEqual([s.calls[1].patch, s.calls[1].version], [{ rate: 2 }, 7]);
    assert.deepEqual(seen.find(([state]) => state === 'stale'), ['stale', 'Changed by someone else.']);
});

test('a failure (or a throw) keeps the edits under later ones, and retry sends them', async () => {
    const s = server();
    const saver = createRowSaver({ send: s.send, debounce: 0, version: () => 1 });
    saver.change('a', { rate: 1, label: 'x' });
    await tick(5);
    s.answer(0, { ok: false, conflict: false });
    await tick();
    assert.equal(saver.state('a'), 'failed');
    saver.change('a', { label: 'y' });
    await tick(5);
    assert.equal(s.calls.length, 2, 'a new edit tries again');
    assert.deepEqual(s.calls[1].patch, { rate: 1, label: 'y' });
    s.calls[1].resolve({ ok: false, conflict: false });
    await tick();
    const thrower = createRowSaver({ send: () => Promise.reject(new Error('offline')), debounce: 0 });
    thrower.change('z', { v: 1 });
    await tick(5);
    assert.equal(thrower.state('z'), 'failed');
    void saver.retry('a');
    await tick();
    assert.deepEqual(s.calls[2].patch, { rate: 1, label: 'y' });
});

test('the server copy of a saved row reaches onChange, for server-computed totals', async () => {
    const s = server();
    const got = [];
    const saver = createRowSaver({ send: s.send, debounce: 0, onChange: (id, state, d) => state === 'saved' && got.push(d.row) });
    saver.change('a', { qty: 2 });
    await tick(5);
    s.answer(0, { ok: true, version: 2, row: { qty: 2, total: '20.00' } });
    await tick();
    assert.deepEqual(got, [{ qty: 2, total: '20.00' }]);
});

test('rowSaveFailure: 409 and stale_* / *conflict* codes are conflicts; anything else a failure with its message', () => {
    assert.equal(rowSaveFailure(409, null).conflict, true);
    assert.equal(rowSaveFailure(422, { error: 'stale_write' }).conflict, true);
    assert.equal(rowSaveFailure(422, { code: 'edit_conflict' }).conflict, true);
    assert.deepEqual(rowSaveFailure(422, { message: 'Rate must be positive.' }), { ok: false, conflict: false, reason: 'Rate must be positive.' });
    assert.deepEqual(rowSaveFailure(undefined, null), { ok: false, conflict: false, reason: null });
});

test('useRowAutosave sends through Inertia\'s client with the version (stale-write guard), reads bodies safely, and holds the leave guard while rows are unsaved', () => {
    const source = flatRead('Form/useRowAutosave.ts');
    assert.match(source, /http\.getClient\(\)\.request\(\{ ?method: \(options\.method \?\? 'patch'\)[^}]*data: \{ ?\.\.\.patch, version ?\}/);
    assert.match(source, /responseJson\(response\)/);
    assert.match(source, /errorJson\(error\)/);
    assert.match(source, /hold\(saver\.pending\(\)\)/);
    assert.match(source, /onBeforeUnmount\(\(\) => hold\(false\)\)/);
});

test('rollupRowSaves: stale beats failed beats saving beats unsaved beats saved; all idle is idle', async () => {
    const { rollupRowSaves } = await import(pathToFileURL(join(dir, 'rowSave.ts')).href);
    assert.equal(rollupRowSaves(['saved', 'stale', 'failed', 'saving']), 'conflict');
    assert.equal(rollupRowSaves(['saved', 'failed', 'saving']), 'error');
    assert.equal(rollupRowSaves(['saved', 'saving', 'pending']), 'saving');
    assert.equal(rollupRowSaves(['saved', 'pending']), 'unsaved');
    assert.equal(rollupRowSaves(['idle', 'saved']), 'saved');
    assert.equal(rollupRowSaves(['idle']), 'idle');
    assert.equal(rollupRowSaves([]), 'idle');
});

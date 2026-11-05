import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { flat, members } from './shape.mjs';

// Kit Checklist: checklist.ts (plain TypeScript) runs here; the component and composable are checked by source and typechecked with the kit's clients.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-checklist-'));
writeFileSync(join(dir, 'checklist.ts'), read('Feedback/checklist.ts.stub'));
const { CHECKLIST_STATES, checklistMessage, checklistProgress } = await import(pathToFileURL(join(dir, 'checklist.ts')).href);
const item = (key, state, extra = {}) => ({ key, label: key[0].toUpperCase() + key.slice(1), state, ...extra });

test('build steps: the active step is current, and progress counts what is settled', () => {
    const steps = [item('preparing', 'done'), item('rendering', 'active'), item('saving', 'pending')];
    const progress = checklistProgress(steps);
    assert.deepEqual({ ...progress, current: progress.current.key }, { total: 3, settled: 1, done: 1, failed: 0, complete: false, current: 'rendering', percent: 33 });
    assert.equal(checklistMessage(progress), 'Rendering');
});

test('complete only when every required step is done or skipped and nothing failed; optional steps never hold it back', () => {
    assert.equal(checklistProgress([item('a', 'done'), item('b', 'skipped')]).complete, true);
    assert.equal(checklistProgress([item('a', 'done'), item('b', 'pending', { required: false })]).complete, true, 'an optional pending step');
    assert.equal(checklistProgress([item('a', 'done'), item('b', 'failed', { required: false })]).complete, false, 'any failure');
    assert.equal(checklistProgress([item('a', 'done'), item('b', 'active')]).complete, false);
    assert.equal(checklistMessage(checklistProgress([item('a', 'done')])), 'All steps done');
});

test('a failed step is current when nothing is active, and the message says so', () => {
    const progress = checklistProgress([item('preparing', 'done'), item('rendering', 'failed', { reason: 'The PDF renderer timed out.' }), item('saving', 'pending')]);
    assert.equal(progress.current.key, 'rendering');
    assert.equal(checklistMessage(progress), 'Rendering failed');
    assert.equal(checklistMessage(progress, { failed: 'The preview failed' }), 'The preview failed');
});

test('fails closed: an unknown state counts as pending, and no items is not complete', () => {
    assert.equal(checklistProgress([item('a', 'done'), item('b', 'finished')]).complete, false);
    assert.deepEqual(checklistProgress(null), { total: 0, settled: 0, done: 0, failed: 0, complete: false, current: null, percent: 0 });
    assert.deepEqual([...CHECKLIST_STATES], ['pending', 'active', 'done', 'failed', 'skipped']);
});

test('Checklist and useChecklist are exported', () => {
    assert.match(flat(read('Feedback/useChecklist.ts.stub')), /export function useChecklist\(items: MaybeRefOrGetter</);
    const barrel = read('index.ts.stub');
    assert.match(barrel, /export \{ default as Checklist \} from '\.\/Feedback\/Checklist\.vue';/);
    assert.match(barrel, /export \{ useChecklist \} from '\.\/Feedback\/useChecklist';/);
});

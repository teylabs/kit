import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// ActionBar's fit, the stage-anchored toolbar's position and client toolbar actions run here without Vue; the components are checked by source (the kit has no
// browser test runner, so nothing here renders, lays out or observes a resize).
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-editor-gaps-'));
for (const [name, path] of [['actionFit', 'Action/actionFit.ts'], ['toolbarAnchor', 'Layout/toolbarAnchor.ts'], ['toolbarAction', 'Layout/toolbarAction.ts']]) {
    writeFileSync(join(dir, `${name}.ts`), read(path));
}
const { fitActions } = await import(pathToFileURL(join(dir, 'actionFit.ts')).href);
const { anchorToolbar, unionRect } = await import(pathToFileURL(join(dir, 'toolbarAnchor.ts')).href);
const { runToolbarAction } = await import(pathToFileURL(join(dir, 'toolbarAction.ts')).href);

const actions = ['publish', 'preview', 'duplicate', 'archive'].map(key => ({ key }));
const keys = list => list.map(action => action.key);

test('fitActions: a full layout that fits stays whole', () => {
    const fit = fitActions(actions, { container: 400, parts: [120, 100, 80], gap: 12 });
    assert.equal(fit.compact, false);
    assert.deepEqual(keys(fit.inline), ['publish', 'preview', 'duplicate', 'archive']);
    assert.deepEqual(fit.menu, []);
    assert.equal(fitActions(actions, { container: 324, parts: [120, 100, 80], gap: 12 }).compact, false, 'exactly the needed width fits');
    assert.equal(fitActions(actions, { container: 323.6, parts: [120, 100, 80], gap: 12 }).compact, false, 'half a pixel of rounding is forgiven');
});

test('fitActions: one that does not fit keeps the main action and moves the rest, in order, to the menu', () => {
    const fit = fitActions(actions, { container: 358, parts: [120, 100, 80, 70], gap: 12 });
    assert.equal(fit.compact, true);
    assert.deepEqual(keys(fit.inline), ['publish']);
    assert.deepEqual(keys(fit.menu), ['preview', 'duplicate', 'archive']);
    assert.equal(fitActions(actions, { container: 300, parts: [310] }).compact, true, 'one wide group that overflows compacts too');
});

test('fitActions: only the primary action never compacts, however narrow', () => {
    const fit = fitActions([{ key: 'publish' }], { container: 40, parts: [120] });
    assert.equal(fit.compact, false);
    assert.deepEqual(keys(fit.inline), ['publish']);
    assert.deepEqual(fitActions([], { container: 40, parts: [] }), { compact: false, inline: [], menu: [] });
});

test('fitActions: an unmeasured bar (zero or no width) shows everything', () => {
    for (const container of [0, -1, Number.NaN]) {
        const fit = fitActions(actions, { container, parts: [120, 100, 80, 70], gap: 12 });
        assert.equal(fit.compact, false, `container ${container}`);
        assert.equal(fit.inline.length, 4);
    }
});

test('anchorToolbar: centred above the selection inside the stage', () => {
    const stage = { top: 100, left: 50, width: 800, height: 600 };
    const at = anchorToolbar({ top: 300, left: 350, width: 200, height: 100 }, stage, { width: 160, height: 40 });
    assert.deepEqual(at, { top: 300 - 100 - 8 - 40, left: 350 - 50 + 100 - 80, placement: 'top' });
});

test('anchorToolbar: flips below when there is no room above', () => {
    const stage = { top: 100, left: 50, width: 800, height: 600 };
    const at = anchorToolbar({ top: 120, left: 350, width: 200, height: 100 }, stage, { width: 160, height: 40 });
    assert.equal(at.placement, 'bottom');
    assert.equal(at.top, 20 + 100 + 8);
    const tall = anchorToolbar({ top: 110, left: 350, width: 200, height: 590 }, stage, { width: 160, height: 40 });
    assert.equal(tall.top, 600 - 8 - 40, 'no room either way: clamped inside the stage');
});

test('anchorToolbar: clamps at the left and right edges', () => {
    const stage = { top: 0, left: 0, width: 800, height: 600 };
    assert.equal(anchorToolbar({ top: 300, left: 0, width: 40, height: 40 }, stage, { width: 160, height: 40 }).left, 8, 'left');
    assert.equal(anchorToolbar({ top: 300, left: 780, width: 20, height: 40 }, stage, { width: 160, height: 40 }).left, 800 - 8 - 160, 'right');
    assert.equal(anchorToolbar({ top: 300, left: 10, width: 20, height: 40 }, { ...stage, width: 100 }, { width: 160, height: 40 }).left, 8, 'wider than the stage: at the left inset');
});

test('unionRect: the bounding box of the selected elements, null for none', () => {
    assert.equal(unionRect([]), null);
    assert.deepEqual(unionRect([{ top: 10, left: 20, width: 30, height: 40 }, { top: 5, left: 60, width: 10, height: 10 }]), { top: 5, left: 20, width: 50, height: 45 });
});

test('client toolbar actions reach the host through the toolbar\'s own handler, never a disabled one', () => {
    const received = [];
    assert.equal(runToolbarAction(action => received.push(action.key), { key: 'bold', label: 'Bold' }), true);
    assert.equal(runToolbarAction(action => received.push(action.key), { key: 'align', label: 'Align', disabled: true, reason: 'Select text first' }), false);
    assert.equal(runToolbarAction(undefined, { key: 'bold', label: 'Bold' }), false);
    assert.deepEqual(received, ['bold']);
});

